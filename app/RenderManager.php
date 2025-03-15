<?php

namespace App;
use App\Models\Bible;
use App\Models\Process;
use App\Models\Rendering;
use App\Models\RenderLog;
use App\ProcessManager;
use Illuminate\Support\Facades\Gate;

class RenderManager {
    use Traits\Error;

    static public $format_kinds = [
        'pdf'       => [
            'name'      => 'PDF',
            'desc'      => 'Ready-to-print PDF files',
            'formats'   => ['pdf_cpt_let', 'pdf_cpt_a4', 'pdf_cpt_let_ul', 'pdf_cpt_a4_ul'], // 'pdf_normal'],
        ],
        'text'      => [
            'name'      => 'Plain Text',
            'desc'      => '',
            'formats'   => ['text', 'mr_text'],
        ],       
        'spreadsheet'      => [
            'name'      => 'Spreadsheet',
            'desc'      => 'Opens in MS Excel or other spreadsheet software.  Both human and machine readable.',
            'formats'   => ['csv', 'xlsx'],
        ],
        'database' => [
            'name'      => 'Databases',
            'desc'      => 'Databases and database dumps.  Ready to import into your own software.',
            'formats'   => ['json', 'sqlite3', 'mysql', 'biblesupersearch'],
        ],
    ];

    static public $register = [
        'pdf'               => \App\Renderers\PdfCompact::class,
        'pdf_cpt_let'       => \App\Renderers\PdfCompact::class,
        'pdf_cpt_a4'        => \App\Renderers\PdfCompactA4::class,
        // 'pdf_normal'        => \App\Renderers\PdfNormal::class,
        'pdf_cpt_let_ul'    => \App\Renderers\PdfCompactUl::class,
        'pdf_cpt_a4_ul'     => \App\Renderers\PdfCompactUlA4::class,        
        'text'              => \App\Renderers\PlainText::class,
        'mr_text'           => \App\Renderers\MachineReadableText::class,
        'csv'               => \App\Renderers\Csv::class,
        'xlsx'              => \App\Renderers\Excel::class,
        // 'xlsx'              => \App\Renderers\ExcelFromCsv::class,
        'json'              => \App\Renderers\Json::class,
        'sqlite3'           => \App\Renderers\SQLite3::class,
        'mysql'             => \App\Renderers\MySQL::class,
        'biblesupersearch'  => \App\Renderers\BibleSuperSearch::class,
    ];

    protected $Bibles = [];
    protected $format = [];
    protected $modules = [];
    protected $zip = FALSE;
    protected $multi_bibles = FALSE;
    protected $multi_format = FALSE;
    protected $needs_process = FALSE;
    protected $Output = NULL;
    protected $ProgressBar = NULL;
    public $include_extras = FALSE;

    public function __construct($modules, $format, $zip = FALSE, $Output = NULL) 
    {
        $this->multi_bibles = ($modules == 'ALL' || $modules == 'OFFICIAL' || count($modules) > 1);
        $this->multi_format = ($format  == 'ALL' || count($format)  > 1);
        $this->zip = ($this->multi_bibles && $this->multi_format) ? TRUE : $zip;
        $this->Output = $Output ?: null;

        if($this->multi_bibles && $this->multi_format) {
            $this->addError('Cannot request multiple items for both Bible and format!');
            return;
        }

        if($format == 'ALL') {
            $this->format = array_keys(static::$register);
        }
        else {
            $format = (array) $format;

            foreach($format as $fm) {
                if(!array_key_exists($fm, static::$register)) {
                    $this->addError("Format {$fm} does not exist!");
                    continue;
                }

                $this->format[] = $fm;
            }
        }

        if($modules == 'ALL') {
            $this->_selectAllBibles();
        }
        elseif($modules == 'OFFICIAL') {
            $this->_selectOfficialBibles();
        }
        else {
            $modules = (array) $modules;
            $this->modules = $modules;

            foreach($modules as $module) {
                $Bible = Bible::findByModule($module);

                if(!$Bible) {
                    $this->addError( trans('errors.bible_no_exist', ['module' => $module]) );
                    continue;
                }

                if(!$Bible->isDownloadable() && !Gate::allows('admin-access')) {
                    $this->addError( trans('errors.bible_no_download', ['module' => $module]) );
                    continue;
                }

                if(!$this->_checkBibleFormat($Bible)) {
                    continue;
                }

                $this->Bibles[] = $Bible;
            }
        }

        if(config('download.bible_limit') && count($this->modules) > config('download.bible_limit')) {
            $this->addError( trans('errors.to_many_download', ['maximum' => config('download.bible_limit')]) );
        }
    }

    protected function _checkBibleFormat($Bible)
    {
        $success = true;

        foreach($this->format as $format) {
            $CLASS = static::$register[$format];
            $Renderer = new $CLASS($Bible);

            if(!$Renderer->canRenderAndDownload()) {
                $success = false;

                if($Renderer->hasErrors()) {
                    $this->addErrors($Renderer->getErrors());
                } else {
                    $this->addError( trans('errors.download.bible_format_not_ava', [
                        'name'   => $Bible->name,
                        'format' => $CLASS::$name
                    ]) );
                }
            }
        }

        return $success;
    }

    protected function _selectAllBibles() 
    {
        $Bibles = Bible::where('enabled', 1) -> get() -> all();

        foreach($Bibles as $Bible) {
            if($Bible->isDownloadable()) {
                $this->Bibles[]  = $Bible;
                $this->modules[] = $Bible->module;
            }
        }

        if(empty($this->Bibles)) {
            $this->addError('No downloadable Bibles installed');
        }
    }

    protected function _selectOfficialBibles() 
    {
        $Bibles = Bible::where('enabled', 1) -> where('official', 1) -> get() -> all();

        foreach($Bibles as $Bible) {
            if($Bible->isDownloadable()) {
                $this->Bibles[]  = $Bible;
                $this->modules[] = $Bible->module;
            }
        }

        if(empty($this->Bibles)) {
            $this->addError('No downloadable Bibles installed');
        }
    }

    public function separateProcessSupported() {
        return FALSE;
    }

    public function getBiblesNeedingRender($format = NULL, $overwrite = FALSE, $bypass_render_limit = FALSE, $limit_override = NULL) {
        $format = $format ?: $this->format[0];
        $CLASS = static::$register[$format];
        $limit = isset($limit_override) ? $limit_override : $CLASS::getRenderBiblesLimit();
        $CLASS::$load_fonts = FALSE;
        $limit = $limit == 0 ? true : $limit;

        if($overwrite) {
            $Bibles_Needing_Render = $this->Bibles;
        }
        else {
            $Bibles_Needing_Render = [];

            foreach($this->Bibles as $Bible) {
                $Renderer = new $CLASS($Bible);

                if($Renderer->isRenderNeeded()) {
                    $Bibles_Needing_Render[] = $Bible;
                }
            }
        }
        
        $CLASS::$load_fonts = TRUE;

        if(!$bypass_render_limit && $limit !== TRUE && count($Bibles_Needing_Render) > $limit) {
            // create detatched process on 'php artisan queue:work --once ONLY' if jobs table is EMPTY
            // $this->_createDetatchedProcess($format, $Bibles_Needing_Render, $overwrite);
            $this->needs_process = TRUE;
            $this->addError('The requested Bibles will take a while to render.  Please come back in an hour and try your download again.');
        }

        return $Bibles_Needing_Render;
    }

    public function render($overwrite = FALSE, $suppress_overwrite_error = TRUE, $bypass_render_limit = FALSE) 
    {
        if($this->hasErrors()) {
            return FALSE;
        }

        set_time_limit(0);
        
        if(count($this->modules) > 1 && !$this->cleanUpTempFiles()) {
            return FALSE;
        }

        $error_reporting_cache = error_reporting();
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
        $this->needs_process = FALSE;

        try {
            foreach($this->format as $format) {
                $CLASS = static::$register[$format];
                $Bibles_Needing_Render = $this->getBiblesNeedingRender($format, $overwrite, $bypass_render_limit);

                if($Bibles_Needing_Render === FALSE) {
                    return FALSE;
                }

                if($this->Output && count($Bibles_Needing_Render) > 0) {
                    $this->Output->write("Rendering bibles \n");
                    $this->ProgressBar = $this->Output->createProgressBar(count($Bibles_Needing_Render));
                    $this->ProgressBar->setFormatDefinition('custom', ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% -- %message%                     ' . PHP_EOL);
                    $this->ProgressBar->setFormat('custom');
                }
                
                foreach($Bibles_Needing_Render as $Bible) {
                    if(!static::isRenderWritable($format, $Bible->module)) {
                        $this->addError('Unable to render ' . $Bible->name . ', could not write to file.  Please contact system administrator');
                        continue;
                    }

                    $this->Output && $this->ProgressBar->setMessage($Bible->name);

                    $Renderer = new $CLASS($Bible);

                    if(!$Renderer->render(TRUE, $suppress_overwrite_error)) {
                        $this->addErrors($Renderer->getErrors(), $Renderer->getErrorLevel());
                    }
                    
                    if($this->Output) {
                        $this->ProgressBar->advance();
                    }

                }

                if($this->Output && count($Bibles_Needing_Render) > 0) {
                    $this->ProgressBar->setMessage('');
                    $this->ProgressBar->finish();
                }
            }
        }
        catch (\Exception $e) {
            if( env('APP_ENV', 'production') == 'production') {
                return $this->addError($e->getMessage());
            }

            throw $e;
        }

        error_reporting($error_reporting_cache);
        return !$this->hasErrors();
    }

    public function renderExtras($overwrite = FALSE, $error_if_not_applicable = FALSE, $return_file_list = FALSE) 
    {
        $ExtrasRenderer = NULL;

        try {
            foreach($this->format as $format) {
                $CLASS = static::$register[$format];

                $EXTRAS_CLASS = $CLASS::$extras_class;

                if(!$EXTRAS_CLASS) {
                    if($error_if_not_applicable) {
                        $this->addError('Renderer does not have any extras: ' . $format, 1);
                    }

                    continue;
                }

                $ExtrasRenderer = new $EXTRAS_CLASS();
                $ExtrasRenderer->render($overwrite);
            }
        }
        catch (\Exception $e) {
            return $this->addError($e->getMessage());
        }

        if($ExtrasRenderer) {
            return ($return_file_list) ? $ExtrasRenderer->getFileList() : TRUE;
        }

        return FALSE;
    }

    public function download($bypass_render_limit = FALSE, $make_file_only = false, $en_lang_name = false) 
    {

        if($this->hasErrors()) {
            return FALSE;
        }

        $download_limit = config('download.bible_limit');

        if($download_limit && count($this->Bibles) > $download_limit) {
            return $this->addError('You can download a maximum of ' . $download_limit . ' Bibles at once.');
        }

        $rs = $this->render(FALSE, TRUE, $bypass_render_limit);

        if(!$rs) {
            return FALSE;
        }

        $download_file_path = NULL;
        $delete_file = FALSE;

        $mb_str_pad = function($input, $pad_length, $pad_string = ' ', $pad_style = STR_PAD_RIGHT, $encoding="UTF-8") {
            return str_pad($input, strlen($input) - mb_strlen($input,$encoding) + $pad_length, $pad_string, $pad_style);
        };

        if(isset($_SERVER['REMOTE_ADDR'])) {
            RenderLog::deleteByIp($_SERVER['REMOTE_ADDR'], 300);
        }

        if($this->multi_bibles || $this->multi_format || $this->zip) {
            $date = new \DateTime();
            $zip_filename = 'truth_' . $date->format('Ymd_His_u') . '.zip';

            $group_by_language = true;

            // Create Zip File in tmp dir
            // $dir = sys_get_temp_dir();
            $dir = Renderers\RenderAbstract::getRenderBasePath(); // . 'temp_zip/';
            $delete_file = TRUE;
            $zip_path = $dir . $zip_filename;

            $readme = "Bible SuperSearch Bible Export\n\n";

            usort($this->Bibles, function($a, $b) {
                return strcmp($a->lang_short, $b->lang_short);
            });

            try {
                $Zip = new \ZipArchive;

                if(!$Zip->open($zip_path, \ZipArchive::CREATE)) {
                    return $this->addError('Unable to create ZIP file <tmppath>/' . $zip_filename);
                }

                if(isset($_SERVER['REMOTE_ADDR'])) {
                    $Log = new RenderLog;
                    $Log->module = 'ALL';
                    $Log->filename = $zip_filename;
                    $Log->ip_address = $_SERVER['REMOTE_ADDR'];
                    $Log->save();
                }

                // Copy all appropiate files into Zip file
                foreach($this->format as $format) {
                    $readme_cache = $language_cache = [];
                    $CLASS = static::$register[$format];

                    if($this->Output && count($this->Bibles) > 0) {
                        $this->Output->write("Adding bibles to .ZIP file \n");
                        $this->ProgressBar = $this->Output->createProgressBar(count($this->Bibles));
                        $this->ProgressBar->setFormatDefinition('custom', ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% -- %message%                     ' . PHP_EOL);
                        $this->ProgressBar->setFormat('custom');
                    }

                    $readme .= strip_tags( $CLASS::getName() ) . "\n";
                    $readme .= strip_tags( $CLASS::getDescription() ) . "\n\n\n";
                    $readme .= "These Bibles are legally shareable and reshareable for non-commercial purposes.  Please share them with others.\n\n";
                    $readme .= "Please see the copyright statement on each Bible for more information.\n\n";
                    $readme .= "These files are provided as-is and without warranty.\n\n";

                    $readme .= "\nIndex of Bibles Included: \n\n";
                    
                    if($group_by_language) {
                        $readme .= 'File' . str_repeat(' ', 40) . "Bible\n";
                    } else {
                        $readme .= 'File' . str_repeat(' ', 40) . 'Bible' . str_repeat(' ', 76) . "Language\n";
                    }

                    $readme .= str_repeat('=', 160) . "\n";

                    foreach($this->Bibles as $Bible) {
                        $Renderer = new $CLASS($Bible);
                        $filepath = $Renderer->getDownloadFilePath();

                        if(!$filepath) {
                            continue;
                        }

                        if($this->Output) {
                            $this->ProgressBar->setMessage($Bible->name);

                        }

                        $lang = trim($Bible->language->native_name);
                        $lang .= ($Bible->language->name != $Bible->language->native_name) ? ' (' . $Bible->language->name . ')' : '';
                        $display_name = $Bible->name;

                        // Add year to display name if it exists and is not already there
                        $display_name .= ($Bible->year && !str_contains($display_name, $Bible->year)) ? ' (' . $Bible->year . ')' : '';
                        $display_filename = basename($filepath);
                        
                        if($en_lang_name) {
                            $lang_dir = strtoupper($Bible->lang_short) . '-' . str_replace(' ', '_', trim($Bible->language->name));
                        } else {
                            $lang_dir = strtoupper($Bible->lang_short) . '-' . str_replace(' ', '_', trim($Bible->language->native_name));
                        }

                        if($group_by_language) {
                            $filename = $lang_dir . '/' . basename($filepath);
                        } else {
                            $filename = basename($filepath);
                        }

                        if($group_by_language && !isset($language_cache[$Bible->lang_short])) {
                            if($en_lang_name) {
                                $lang_display = $Bible->lang_short == 'en' ? '' : '   (' . $Bible->language->native_name . ')';
                            } else {
                                $lang_display = $Bible->lang_short == 'en' ? '' : '   (' . $Bible->language->name . ')';
                            }
                            
                            $readme_cache[$lang_dir] = "\n\n" . $lang_dir . $lang_display . "\n" . str_repeat('-', 140) . "\n";
                            $language_cache[$Bible->lang_short] = true;
                        }

                        $readme_cache[$filename]  = '';
                        $readme_cache[$filename] .= '* ' . $mb_str_pad($display_filename . ' ', 40, '-');
                        
                        if($group_by_language) {
                            $readme_cache[$filename] .= '- ' . $display_name;
                        } else {
                            $readme_cache[$filename] .= '- ' . $mb_str_pad($display_name . ' ', 80, '-') . ' ';
                            $readme_cache[$filename] .= $lang;
                        }

                        $readme_cache[$filename] .= "\n";

                        if( !$Zip->addFile($filepath, $filename) ) {
                            return $this->addError('Unable to add Bible to ZIP file: ' . $Bible->name);
                        }
                        
                        $Renderer->incrementHitCounter();

                        if($this->Output) {
                            $this->ProgressBar->advance();
                        }
                    }

                    if($this->Output) {
                        $this->ProgressBar->setMessage('');
                        $this->ProgressBar->finish();
                    }

                    ksort($readme_cache);
                    $readme .= implode('', $readme_cache);
                    $readme .= "\n\n";
                }

                if($this->include_extras) {
                    $file_list = $this->renderExtras(FALSE, FALSE, TRUE);
                    
                    if(!empty($file_list)) {
                        $Zip->addEmptyDir('extras');
                        $readme .= "\n\nextras - This folder contains additional helpful items\n\n";

                        foreach($file_list as $file) {
                            if(!$Zip->addFile($file, 'extras/' . basename($file)) ) {
                                return $this->addError('Unable to add file to ZIP file: ' . $file['file']);
                            }
                        }
                    }
                }

                $Zip->addFromString('readme.txt', $readme);
                $Zip->close();   
            }
            catch (\Exception $e) {
                return $this->addError($e->getMessage());
            }

            // Send Zip file to browser as download
            $download_file_path = $zip_path;
            $download_file_name = $zip_filename;
        }
        else {
            $format     = $this->format[0];
            $Bible      = $this->Bibles[0];
            $CLASS      = static::$register[$format];
            $Renderer   = new $CLASS($Bible);
            $Renderer->incrementHitCounter();
            $download_file_path = $Renderer->getDownloadFilePath();
            $download_file_name = basename($download_file_path);

            if(isset($_SERVER['REMOTE_ADDR'])) {
                $Log = new RenderLog;
                $Log->module = $Bible->module;
                $Log->filename = $Renderer->getRenderFilePath(false, true);
                $Log->ip_address = $_SERVER['REMOTE_ADDR'];
                $Log->save();
            }

            // Send file to browser as download
        }

        if(!$make_file_only && file_exists($download_file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $download_file_name);
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($download_file_path));

            try {
                // ini_set('memory_limit','256M');
                readfile($download_file_path);
            }
            catch (\Exception $e) {
                unlink($download_file_path);
                return $this->addError($e->getMessage());
            }

            if($delete_file) {
                unlink($download_file_path);
            }

            exit;
        }
        else {
            if(!$make_file_only) {
                return $this->addError('Unknown error - download file no longer exists');
            }
        }
    }

    public function needsProcess() 
    {
        return $this->needs_process;
    }

    public static function deleteAllFiles($dry_run = FALSE) 
    {
        if($dry_run) {
            return TRUE;
        }

        $DeletableRenderings = Rendering::whereNotNull('rendered_at')->get();

        foreach ($DeletableRenderings as $DR) {
            $DR->deleteRenderedFile();
        }

        return TRUE;
    }

    /**
     * Deletes files as needed to make room for the current batch
     */
    public function cleanUpFiles($dry_run = FALSE) 
    {
        $CLASS = $this->getRenderClass();
        $RendererId = $CLASS::getRendererId();
        $modules_has_file = $modules_no_file = [];
        $modules = $this->modules;

        foreach($this->Bibles as $Bible) {
            $Renderer = new $CLASS($Bible);

            if(file_exists($Renderer->getRenderFilePath())) {
                $modules_has_file[] = $Bible->module;
            }
            else {
                $modules_no_file[] = $Bible->module;
            }
        }

        $cur_space = static::getUsedSpace();
        $est_space = $this->getEstimatedSpace( count($modules_no_file) );
        $est_space_all = $this->getEstimatedSpace( count($modules) );

        $retain             = (bool) config('download.retain');
        $cache_size         = ($retain) ? (int) config('download.cache.cache_size') : 0;
        $temp_cache_size    = (int) config('download.cache.temp_cache_size') ?: FALSE;

        $max_space = $cache_size + $temp_cache_size;

        if($est_space_all > $max_space) {
            return $this->addError('Not enough space allocated to render all of the selected Bibles.  Please reduce the amount of Bibles selected.');
        }
        
        $total_space = $cur_space + $ext_space;

        if($total_space <= $max_space) {
            $needed_space = 0; // Because this operation doesn't need any space freed up
        }
        else {
            $needed_space = $total_space - $max_space;
        }

        // 'WHERE rendered_at IS NOT NULL AND (renderer != :renderer OR module NOT IN () )'; // WHERE needs to be in this form!
        $DeletableQuery = Rendering::whereNotNull('rendered_at') -> where( function($Query) use ($RendererId, $modules) {
            $Query  -> where('renderer', '!=', $RendererId)
                    -> orWhereNotIn('module', $modules);
        });

        static::_deletableQueryAddSort($DeletableQuery);

        $DeletableRenderings = $DeletableQuery->get();

        $success = static::_cleanUpFilesHelper($DeletableRenderings, $needed_space, $dry_run);

        if(!$success) {
            return $this->addError('Unknown error while cleaning up files, please contact system administrator');
        }

        return TRUE;
    }

    public static function cleanUpTempFiles($dry_run = FALSE) 
    {
        $DeletableQuery = Rendering::whereNotNull('rendered_at');
        static::_deletableQueryAddSort($DeletableQuery);
        $Renderings = $DeletableQuery->get();
        return static::_cleanUpFilesHelper($Renderings, 0, $dry_run);
    }

    public static function cleanUpTempZipFiles($dry_run = false)
    {
        $render_base_path = \App\Renderers\RenderAbstract::getRenderBasePath();
        $threshold = 3600; // 1 hour in seconds

        $handle = opendir($render_base_path);
        $deleted_files = [];

        while(false !== ($entry = readdir($handle))) {
            if($entry == '.' || $entry == '..') {
                continue;
            }

            $path = $render_base_path . $entry;

            if(!is_file($path) || $path == 'readme.txt') {
                continue;
            }

            $ext = explode('.', $entry);
            $ext = end($ext);

            if($ext == 'zip' && substr($entry, 0, 6) == 'truth_') {
                $mod_time = filemtime($path);

                if(time() - $mod_time < $threshold) {
                    continue;
                }
                
                if($dry_run) {
                    $deleted_files[] = $entry;
                } else {
                    unlink($path);
                }
            }
        }

        closedir($handle);

        return $dry_run ? $deleted_files : true;
    }

    private static function _deletableQueryAddSort(&$DeletableQuery) 
    {
        // Todo: Make this ordering a config?
        // This will need continued tweaking
        $DeletableQuery->orderBy('rendered_duration', 'desc');
        $DeletableQuery->orderBy('hits', 'asc');
        $DeletableQuery->orderBy('file_size', 'desc');
        $DeletableQuery->orderBy('custom', 'desc');
        $DeletableQuery->oldest('downloaded_at');
        // $DeletableQuery->oldest('rendered_at');
    }

    public static function _testCleanUpFiles($space_needed_render = 0, $verbose = FALSE, $debug_overrides = []) 
    {
        $DeletableQuery = Rendering::whereNotNull('rendered_at');
        static::_deletableQueryAddSort($DeletableQuery);
        $Renderings = $DeletableQuery->get();
        return static::_cleanUpFilesHelper($Renderings, $space_needed_render, TRUE, $verbose, $debug_overrides);
    }

    private static function _cleanUpFilesHelper($DeletableRenderings, $space_needed_render = 0, $dry_run = FALSE, $verbose = FALSE, $debug_overrides = []) 
    {
        $retain             = (bool) config('download.retain');
        $min_render_time    = config('download.cache.min_render_time') ?: FALSE;
        $min_hits           = config('download.cache.min_hits') ?: FALSE;
        $cache_size         = config('download.cache.cache_size') ?: FALSE;
        $cache_size         = ($retain) ? $cache_size : 0;
        $temp_cache_size    = config('download.cache.temp_cache_size') ?: FALSE;
        $days               = config('download.cache.days') ?: FALSE;
        $max_filesize       = config('download.cache.max_filesize') ?: FALSE;
        $cur_space          = static::getUsedSpace();
        $comp_date          = NULL;
        $deleted_files      = [];

        if($dry_run) {
            if(is_array($debug_overrides)) {
                foreach($debug_overrides as $key => $value) {
                    if($verbose) {
                        print "\n$" . $key . ' = ' . $value;
                    }
                    $$key = $value;
                }
            }

            $dry_run_vars = compact('cur_space','space_needed_render');
            $dry_run_vars['freed_space'] = -1;
            $dry_run_vars['error'] = FALSE;
        }

        $dry_run_verbose = ($dry_run && $verbose);
        $space_needed_render = ($space_needed_render < 0 ) ? 0 : $space_needed_render;
        $space_needed_cache = $space_needed_overall = $freed_space = $space_needed_extra = 0;
        $cache_size_max     = (int) $cache_size + (int) $temp_cache_size;

        if($space_needed_render > $cache_size_max) {
            if($dry_run) {
                $dry_run_vars['error'] = 'Too much space needed';
                return $dry_run_vars;
            }

            return FALSE;
        }

        if($cur_space > $cache_size) {
            $space_needed_cache = $cur_space - $cache_size;

            if($space_needed_render > $temp_cache_size) {
                $space_needed_extra = $space_needed_render - $temp_cache_size;
                $space_needed_overall = $space_needed_cache + $space_needed_extra;
            }
            else {
                $space_needed_overall = $space_needed_cache;
            }
        }
        else {
            if($space_needed_render + $cur_space > $cache_size_max) {
                $space_needed_extra = $space_needed_render + $cur_space - $cache_size_max;
                // $space_needed_overall = $space_needed_extra + $space_needed_render;
                $space_needed_overall = $space_needed_extra;
            }
            else {
                // $space_needed_overall = $space_needed_render;
                $space_needed_overall = 0;
            }
        }

        if($days) {
            $comp_date = strtotime('today -' . $days . ' days');
        }

        if($dry_run_verbose) {
            print "\nspace needed render: " . $space_needed_render;
            print "\nspace needed cache: " . $space_needed_cache;
            print "\nspace needed overall: " . $space_needed_overall;
            print "\nmin render time: " . $min_render_time;
            print "\ncomp date: " . date('Y-m-d H:i:s', $comp_date) . "\n\n";
        }

        foreach($DeletableRenderings as $key => $R) {
            $delete = FALSE;

            if($R->isPendingDownload()) {
                continue; // don't delete if it hasn't been downloaded yet
            }

            if($min_render_time && $R->rendered_duration < $min_render_time) {
                static::_cleanUpDryRunMessage($dry_run_verbose, $R, 'min_render_time');
                $delete = TRUE;
            }           

            if($min_hits && $R->hits < $min_hits) {
                static::_cleanUpDryRunMessage($dry_run_verbose, $R, 'min_hits');
                $delete = TRUE;
            }

            if($max_filesize && $R->file_size > $max_filesize) {
                static::_cleanUpDryRunMessage($dry_run_verbose, $R, 'max_filesize');
                $delete = TRUE;
            }

            if($days) {
                $date = $R->downloaded_at ?: $R->rendered_at;
                $date_ts = strtotime($date);

                if($date_ts < $comp_date) {
                    $delete = TRUE;
                    $dry_run_verbose && static::_cleanUpDryRunMessage($dry_run_verbose, $R, 'days: ' . date('Y-m-d H:i:s', $date_ts));
                }
            }

            if($delete) {
                $freed_space += $R->file_size;
                $deleted_files[] = $R->getRenderedFilePath();

                if(!$dry_run) {
                    $R->deleteRenderedFile();
                }

                unset($DeletableRenderings[$key]);
            }
        }

        if($space_needed_overall > $freed_space) {
            foreach($DeletableRenderings as $R) {
                if($R->isPendingDownload()) {
                    continue; // don't delete if it hasn't been downloaded yet
                }

                $freed_space += $R->file_size;
                static::_cleanUpDryRunMessage($dry_run_verbose, $R, 'more_space_needed');
                $deleted_files[] = $R->getRenderedFilePath();
                
                if(!$dry_run) {
                    $R->deleteRenderedFile();
                }

                if($freed_space >= $space_needed_overall) {
                    break;
                }
            }
        }

        if($dry_run_verbose) {
            print "space in use: {$cur_space}\n\n";
            print "cache size max: {$cache_size_max}\n\n";
            print "space needed overall: {$space_needed_overall}\n\n";
            print "space freed: {$freed_space}\n\n";
        }

        if($dry_run) {
            $deleted_files = array_merge($deleted_files, static::cleanUpTempZipFiles(true));
            
            return compact('cur_space', 'space_needed_overall', 'freed_space', 'space_needed_render', 'deleted_files');
        }

        static::cleanUpTempZipFiles(false);

        if($space_needed_overall > $freed_space && $space_needed_render > 0) {
            // echo "$space_needed_overall / $freed_space";
            return FALSE;
        }

        return TRUE;
    }

    private static function _cleanUpDryRunMessage($dry_run, $Rendering, $config) {
        if($dry_run) {
            print "Deleting {$Rendering->renderer}/{$Rendering->file_name} -> {$config} \n\n";
        }
    }

    public static function getUsedSpace() {
        return Rendering::whereNotNull('rendered_at')->sum('file_size');
    }

    /**
      * Returns the estimated space needed to render the selected Bible(s) in the selected format
      *
      */
    public function getEstimatedSpace($number_of_bibles = NULL) {
        $CLASS = $this->getRenderClass();
        $number_of_bibles = $number_of_bibles ?: count($this->Bibles);
        return $CLASS::$render_est_size * $number_of_bibles;
    }

    public function getRenderClass() {
        $format = $this->format[0];
        return static::$register[$format];
    }

    // Not currently used.  Retaining for later
    protected function _createDetatchedProcess($format, $Bibles_Needing_Render, $overwrite = FALSE) {
        $Pending = Process::where('status', 'pending')->where('form_action', 'download')->get()->all();

        $pending_bibles = $process_bibles = [];

        foreach($Pending as $Process) {
            $data = json_decode($Process->form_data);
            $pending_bibles = array_merge($pending_bibles, $data->bible);
        }

        foreach($Bibles_Needing_Render as $Bible) {
            if(!in_array($Bible->module, $pending_bibles)) {
                $process_bibles[] = $Bible->module;
            }
        }

        if(empty($process_bibles)) {
            return;
        }

        $cmd = 'php ' . $_SERVER['DOCUMENT_ROOT'] . '../artisan bible:render ' . $format . ' "' . implode(',', $process_bibles) . '"'; 

        if($overwrite) {
            $cmd .= ' --overwrite';
        }

        // $cmd .= ' > /dev/null 2>&1';
        // $cmd .= ' > /dev/null & ';
        // $cmd .= ' > /dev/null ';
        $cmd .= ' > ' . $_SERVER['DOCUMENT_ROOT'] . '../bibles/rendered/log_' . time() . '.txt';

        // Use Laravel queues???

        // See these options on php artisan queue:work
        //  --once
        //  --stop-when-empty

        // var_dump(getcwd());

        // die($cmd);

        exec($cmd);
        return TRUE;

        $handle = popen($cmd, 'r');

        echo "handle: '$handle'; " . gettype($handle) . "\n";
        $read = fread($handle, 2096);
        echo 'read' . $read;

        if(!is_resource($handle)) {
            return $this->addError('Unable to start render process');
        }

        pclose($handle);
        return TRUE;
    }

    static public function getRendererList() {
        $list = [];

        foreach(static::$register as $format => $CLASS) {
            $list[$format] = [
                'format' => $format,
                'name'   => $CLASS::getName(),
                'desc'   => $CLASS::getDescription(),
                'CLASS'  => $CLASS,
            ];
        }

        return $list;
    }    

    static public function getGroupedRendererList() {
        $list = [];

        foreach(static::$format_kinds as $kind => $info) {
            $list[$kind] = $info;
            $list[$kind]['renderers'] = [];

            foreach($info['formats'] as $format) {
                $CLASS = static::$register[$format];

                $list[$kind]['renderers'][$format] = [
                    'format' => $format,
                    'name'   => $CLASS::getName(),
                    'desc'   => $CLASS::getDescription(),
                    'CLASS'  => $CLASS,
                ];
            }
        }

        return $list;
    }

    static public function getRenderFilepath($format, $module) {
        $basedir = Renderers\RenderAbstract::getRenderBasePath();
        $CLASS = static::$register[$format];

        if(!$format || !$CLASS) {
            return FALSE;
        }

        $cc = explode('\\', $CLASS);
        $basename = array_pop($cc);

        return $basedir . $basename . '/' . $module;
    }

    static public function isRenderWritable($format = NULL, $module = NULL) {
        if($format && $module) {
            $pcs = explode('/', static::getRenderFilepath($format, $module));
            $file = array_pop($pcs);
            $dir = implode('/', $pcs);

            if(!is_dir($dir)) {
                $dir = Renderers\RenderAbstract::getRenderBasePath();
            }
        }
        else {
            $dir = Renderers\RenderAbstract::getRenderBasePath();
        }

        return is_writable($dir);
    }
}
