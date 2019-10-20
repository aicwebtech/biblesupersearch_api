<?php

namespace App;
use App\Models\Bible;
use App\Models\Process;
use App\ProcessManager;

class RenderManager {
    use Traits\Error;

    static public $format_kinds = [
        'pdf'       => [
            'name'      => 'PDF',
            'desc'      => 'Ready-to-print PDF files',
            'formats'   => ['pdf_cpt_let', 'pdf_cpt_a4', 'pdf_cpt_let_ul', 'pdf_cpt_a4_ul'],
        ],
        'text'      => [
            'name'      => 'Plain Text',
            'desc'      => '',
            'formats'   => ['text', 'mr_text'],
        ],       
        'spreadsheet'      => [
            'name'      => 'Spreadsheet',
            'desc'      => 'Opens in MS Excel or other spreadsheet software.  Both human and machine readable.',
            'formats'   => ['csv'],
        ],
        'database' => [
            'name'      => 'Databases',
            'desc'      => 'Databases and database dumps.  Ready to import into your own software',
            'formats'   => ['csv', 'pdf'],
        ],
    ];

    static public $register = [
        'pdf'               => \App\Renderers\PdfCompact::class,
        'pdf_cpt_let'       => \App\Renderers\PdfCompact::class,
        'pdf_cpt_a4'        => \App\Renderers\PdfCompactA4::class,  
        'pdf_cpt_let_ul'    => \App\Renderers\PdfCompactUl::class,
        'pdf_cpt_a4_ul'     => \App\Renderers\PdfCompactUlA4::class,        
        'text'              => \App\Renderers\PlainText::class,
        'mr_text'           => \App\Renderers\MachineReadableText::class,
        'csv'               => \App\Renderers\Csv::class,
    ];

    protected $Bibles = [];
    protected $format = [];
    protected $zip = FALSE;
    protected $multi_bibles = FALSE;
    protected $multi_format = FALSE;
    protected $needs_process = FALSE;

    public function __construct($modules, $format, $zip = FALSE) {
        $this->multi_bibles = ($modules == 'ALL' || count($modules) > 1) ? TRUE : FALSE;
        $this->multi_format = ($format  == 'ALL' || count($format)  > 1) ? TRUE : FALSE;
        $this->zip = ($this->multi_bibles && $this->multi_format) ? TRUE : $zip;

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
        else {
            $modules = (array) $modules;

            foreach($modules as $module) {
                $Bible = Bible::findByModule($module);

                if(!$Bible) {
                    $this->addError( trans('errors.bible_no_exist', ['module' => $module]) );
                    continue;
                }

                if(!$Bible->isDownloadable()) {
                    $this->addError( trans('errors.bible_no_download', ['module' => $module]) );
                    continue;
                }

                $this->Bibles[] = $Bible;
            }
        }
    }

    protected function _selectAllBibles() {
        $Bibles = Bible::where('enabled', 1) -> get() -> all();

        foreach($Bibles as $Bible) {
            if($Bible->isDownloadable()) {
                $this->Bibles[] = $Bible;
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

        if(!$bypass_render_limit && $limit !== TRUE && count($Bibles_Needing_Render) > $limit) {
            // create detatched process on 'php artisan queue:work --once ONLY' if jobs table is EMPTY
            // $this->_createDetatchedProcess($format, $Bibles_Needing_Render, $overwrite);
            $this->needs_process = TRUE;
            return $this->addError('The requested Bibles will take a while to render.  Please come back in an hour and try your download again.');
        }

        return $Bibles_Needing_Render;
    }

    public function render($overwrite = FALSE, $suppress_overwrite_error = TRUE, $bypass_render_limit = FALSE) {
        if($this->hasErrors()) {
            return FALSE;
        }

        set_time_limit(0);
        $error_reporting_cache = error_reporting();
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
        $this->needs_process = FALSE;

        foreach($this->format as $format) {
            $CLASS = static::$register[$format];

            $Bibles_Needing_Render = $this->getBiblesNeedingRender($format, $overwrite, $bypass_render_limit);

            if($Bibles_Needing_Render === FALSE) {
                return FALSE;
            }
            
            foreach($Bibles_Needing_Render as $Bible) {
                $Renderer = new $CLASS($Bible);

                // if(!$Renderer->render($overwrite, $suppress_overwrite_error)) {
                if(!$Renderer->render(TRUE, $suppress_overwrite_error)) {
                    $this->addErrors($Renderer->getErrors(), $Renderer->getErrorLevel());
                }
            }
        }

        error_reporting($error_reporting_cache);
        return !$this->hasErrors();
    }

    public function download($bypass_render_limit = FALSE) {
        if($this->hasErrors()) {
            return FALSE;
        }

        $rs = $this->render(FALSE, TRUE, $bypass_render_limit);

        if(!$rs) {
            return FALSE;
        }

        $download_file_path = NULL;
        $delete_file = FALSE;

        if($this->multi_bibles || $this->multi_format || $this->zip) {
            $date = new \DateTime();
            $zip_filename = 'truth_' . $date->format('Ymd_His_u') . '.zip';

            // Create Zip File in tmp dir
            // $dir = sys_get_temp_dir();
            $dir = Renderers\RenderAbstract::getRenderBasePath(); // . 'temp_zip/';
            $delete_file = TRUE;
            $zip_path = $dir . $zip_filename;
            $Zip = new \ZipArchive;

            if(!$Zip->open($zip_path, \ZipArchive::CREATE)) {
                return $this->addError('Unable to create ZIP file <tmppath>/' . $zip_filename);
            }

            // Copy all appropiate files into Zip file
            foreach($this->format as $format) {
                $CLASS = static::$register[$format];

                foreach($this->Bibles as $Bible) {
                    $Renderer = new $CLASS($Bible);
                    $filepath = $Renderer->getRenderFilePath();
                    $Zip->addFile($filepath, basename($filepath));
                }
            }

            $Zip->close();

            // Send Zip file to browser as download
            $download_file_path = $zip_path;
            $download_file_name = $zip_filename;
        }
        else {
            $format     = $this->format[0];
            $Bible      = $this->Bibles[0];
            $CLASS      = static::$register[$format];
            $Renderer   = new $CLASS($Bible);
            $download_file_path = $Renderer->getRenderFilePath();
            $download_file_name = basename($download_file_path);

            // Send file to browser as download
        }

        if(file_exists($download_file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $download_file_name);
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($download_file_path));

            readfile($download_file_path);

            if($delete_file) {
                unlink($download_file_path);
            }

            exit;
        }
        else {
            return $this->addError('Unknown error - download file no longer exists');
        }
    }

    public function needsProcess() {
        return $this->needs_process;
    }

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

    static public function cleanUp() {

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
}
