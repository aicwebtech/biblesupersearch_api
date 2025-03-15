<?php

namespace App\Renderers;

use App\Models\Bible;
use App\Models\Rendering;
use DB;
use App;

abstract class RenderAbstract 
{
    use \App\Traits\Error;

    static public $name;
    static public $description = '';

    // Maximum number of Bibles to render with the given format before detatched process is required.   Set to TRUE to never require detatched process.
    static protected $render_bibles_limit = 3; 

    // All render classes must have this - indicates the version number of the file.  Must be changed if the file is changed, to trigger re-rendering.
    static protected $render_version = 0.1;    

    // Estimated time to render a Bible of the given format, in seconds.
    static protected $render_est_time = 60;    

    // Estimated size to render a Bible of the given format, in MB.
    static protected $render_est_size = 6;     

    // Extras rendering class, if any.  Must be child of App\Renderers\Extras\ExtrasAbstract
    static public $extras_class = NULL; 

    static public $load_fonts = TRUE;

    protected $file_extension;

    protected $Bible;

    protected $chunk_size = 100;

    protected $include_book_name = FALSE;
    protected $book_name_language_force = NULL;
    protected $book_name_field = 'name';
    protected $include_special = FALSE;  // Include italics / strongs fields (that may not be used anymore)

    protected $current_book    = NULL;
    protected $current_chapter = NULL;
    protected $chunk_data = [];

    protected $Rendering = NULL;
    protected $overwrite = false;

    protected $connection_map = [];

    public function __construct($module) 
    {
        $this->Bible = ($module instanceof Bible) ? $module : Bible::findByModule($module);

        if(!$this->Bible) {
            $this->addError( trans('errors.bible_no_exist', ['module' => $module]) );
        }

        if(!$this->file_extension) {
            throw new Exception('$this->file_extension is required on render class!');
        }
    }

    public static function renderStrongs($lang)
    {
        return in_array($lang, ['en', 'es', 'zh']);
    }

    /**
     * Generates the output file and saves it to disk
     * @return boolean
     */
    public function render($overwrite = FALSE, $suppress_overwrite_error = FALSE) 
    {
        if($this->hasErrors()) {
            return FALSE;
        }

        set_time_limit(static::$render_est_time + 120);
        $file_path = $this->getRenderFilePath();
        $this->overwrite = $overwrite;

        if(!$overwrite && is_file($file_path)) {
            if($suppress_overwrite_error) {
                return TRUE;
            }

            return $this->addError('File already exists');
        }

        $start_time   = time();
        $locale_cache = App::getLocale();

        App::setLocale($this->Bible->lang_short);

        $success = $this->_renderStart();

        if(!$success) {
            return FALSE;
        }

        $this->_beforeVerseRender();
        $this->_verseRender();
        $this->_afterVerseRender();

        $success = $this->_renderFinish();

        App::setLocale($locale_cache);

        if(posix_getuid() == fileowner($file_path)) {
            chmod($file_path, 0775);
        }

        $file_size_bytes = filesize($this->getRenderFilePath());
        $file_size_mb    = round( $file_size_bytes / 1024 / 1024);

        $Rendering = $this->_getRenderingRecord();
        $Rendering->rendered_duration   = time() - $start_time;
        $Rendering->meta_hash           = md5($this->_getMetaString());
        $Rendering->rendered_at         = date('Y-m-d H:i:s');
        $Rendering->downloaded_at       = NULL;
        $Rendering->version             = static::$render_version;
        $Rendering->file_size           = $file_size_mb;
        $Rendering->file_name           = basename($file_path);
        
        if(!$Rendering->save()) {
            return FALSE;
        }

        return (bool) $success;
    }

    public function isRenderNeeded($ignore_cache = FALSE) 
    {
        $file_path = $this->getRenderFilePath();

        if(!is_file($file_path)) {
            return TRUE;
        }

        $Rendering = $this->_getRenderingRecord($ignore_cache);

        if(static::$render_version != floatval($Rendering->version) || !$Rendering->rendered_at) {
            return TRUE;
        }

        if(md5($this->_getMetaString()) != $Rendering->meta_hash) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * If for any reason the  given format cannot be rendered using the given Bible
     * This will add an error messge and return false
     * (Note: we already check if the given Bible is able to be rendered into any format)
     */ 
    public function canRenderAndDownload()
    {
        return true;
    }

    /**
     * If render file does not exist or output has changed, generates the output file and saves it to disk
     * @return boolean
     */
    public function renderIfNeeded() 
    {
        if($this->isRenderNeeded()) {
            return $this->render(TRUE, TRUE);
        }

        return TRUE;
    }

    public function deleteRenderFile() 
    {
        $Rendering = $this->_getRenderingRecord();
        $file_path = $this->getRenderFilePath();

        if(is_file($file_path)) {
            unlink($file_path);
        }

        $Rendering->rendered_at = NULL;
        $Rendering->save();
    }

    /**
     * Creates a new database connection
     * @param string $name (desired) connection name
     * @param array $settings 
     * @return string $name_adj (actual) connection name 
     */
    protected function createDbConnection($name, $settings)
    {
        $date = new \DateTime();
        $name_adj = $name . '_' . $date->format('YmdHis_u');

        config(['database.connections.' . $name_adj => $settings]);
        $this->connection_map[$name] = $name_adj;
        return $name_adj;
    }

    /**
     * Returns the (actual) connection name for the given connection
     * @param string $name
     * @return string $name_adj
     * @throws \Exception
     */
    protected function getDbConnectionName($name)
    {
        if(!isset($this->connection_map[$name])) {
            throw new \Exception('Connection not found');
        }
        
        return $this->connection_map[$name];
    }

    /**
     * This initializes the file, and does other pre-rendering work
     * @param bool $overwrite
     */
    protected function _renderStart() 
    {
        return TRUE;
    }

    protected function _verseRender()
    {
        $Verses = $this->Bible->verses();
        $table  = $Verses->getTable();
        $Query  = DB::table( $table )->select($table . '.id','book','chapter','verse','text');

        if($this->include_special) {
            $Query->addSelect('italics');
            $Query->addSelect('strongs');
        }

        if($this->include_book_name) {
            $book_table = $this->_getBookTable();
            $Query->join($book_table, $table . '.book', $book_table . '.id');
            $Query->addSelect($book_table . '.' . $this->book_name_field . ' AS book_name');
        }

        $closure = function($rows) {
            foreach($rows as $row) {
                $row->text = $this->_formatText($row->text);
                $this->_renderSingleVerse($row);
            }

            unset($rows);
            $this->_renderVerseChunk();
            $this->chunk_data = [];
        };

        $Query->orderBy($table . '.id');
        $Query->chunk($this->chunk_size, $closure);
        return true;
    }

    protected function _formatText($text)
    {
        if(!static::renderStrongs($this->Bible->lang_short)) {
            $text = preg_replace('/\} \{/', '', $text);
            $text = preg_replace('/\{[^\}]+\}/', '', $text);
        }

        $text = trim($text);

        return $text;
    }

    /**
     * By default, the single verse renderer just inserts the verse data into the chunk data
     */
    protected function _renderSingleVerse($verse) {
        if($this->include_book_name) {
            $this->chunk_data[] = [
                'book_name' => $verse->book_name,
                'book'      => $verse->book,
                'chapter'   => $verse->chapter,
                'verse'     => $verse->verse,
                'text'      => $verse->text,
            ];
        }
        else {
            $this->chunk_data[] = [
                'book'      => $verse->book,
                'chapter'   => $verse->chapter,
                'verse'     => $verse->verse,
                'text'      => $verse->text,
            ];
        }
    }

    protected function _renderVerseChunk() 
    {

    }

    /**
     * Does any nessessary tasks after rendering is finished, such as closing a file stream
     *
     * @return bool $success
     */
    protected function _renderFinish() 
    {
        return TRUE;
    }

    /**
     * Code to be executed before individusl verses are rendered
     * Possible Usage: Title page, preface, copyright info
     */
    protected function _beforeVerseRender() { }

    /**
     * Code to be executed after individusl verses are rendered
     * Usage: Finishing pages
     */
    protected function _afterVerseRender() { }

    protected function _getBookTable() 
    {
        if($this->book_name_language_force) {
            return 'books_' . $this->book_name_language_force;
        }

        if (\App\Models\Books\BookAbstract::isSupportedLanguage($this->Bible->lang_short)) {
            return 'books_' . $this->Bible->lang_short;
        }

        $lang = config('bss.defaults.language_short');

        if (\App\Models\Books\BookAbstract::isSupportedLanguage($lang)) {
            return 'books_' . $lang;
        }

        return 'books_en';
    }

    public function output() {

    }

    public function _getMetaString($plain_text = FALSE) {
        $meta_string = $this->Bible->name;
        $meta_string .= ' ' . $this->_getCopyrightStatement($plain_text);

        return $meta_string;
    }

    protected function _getCopyrightStatement($plain_text = FALSE, $line_break_replacement = NULL) 
    {
        $cr_statement = $this->Bible->getCopyrightStatement();

        if(config('download.derivative_copyright_statement')) {
            $dr_statement = str_replace('YYYY', date('Y'), config('download.derivative_copyright_statement'));
            $dr_statement = nl2br($dr_statement);
            $cr_statement .= '<br /><br />' . $dr_statement;
        }

        if(config('download.app_link_enable')) {
            $app_url = config('app.url');
            $cr_statement .= "<br /><br /><a href='{$app_url}'>{$app_url}</a>";
        }        

        if(config('download.bss_link_enable')) {
            $cr_statement .= "<br /><br /><a href='https://www.biblesupersearch.com'>www.BibleSuperSearch.com</a>";
        }

        return ($plain_text) ? $this->_htmlToPlainText($cr_statement, $line_break_replacement) : $cr_statement;
    }

    public function _getRenderingRecord($ignore_cache = FALSE) 
    {
        if(!$this->Rendering || $ignore_cache) {
            $renderer = static::getRendererId();
            $this->Rendering = Rendering::firstOrCreate(['renderer' => $renderer, 'module' => $this->Bible->module]);
        }

        return $this->Rendering;
    }

    protected function _htmlToPlainText($html, $line_break_replacement = NULL) 
    {
        $line_break_replacement = $line_break_replacement ?: PHP_EOL;
        $line_break_replacement_double = $line_break_replacement . $line_break_replacement;
        $text = $html;
        $text = str_replace(["\r\n", "\n", "\r"], '', $text);
        $text = str_replace(['<br />', '<br>'], $line_break_replacement, $text);
        $text = str_replace(['</p>'], $line_break_replacement_double, $text);
        $text = str_replace('&nbsp;', ' ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = strip_tags($text);
        return $text;
    }

    public function getRenderFilePath($create_dir = FALSE, $relative = false) 
    {
        if($this->hasErrors()) {
            return FALSE;
        }

        $renderer = (new \ReflectionClass($this))->getShortName();
        $module = $this->Bible->module;

        $dir = $relative ? '' : static::getRenderBasePath();
        $dir .= $renderer;

        if(!is_dir($dir) && $create_dir && !relative) {
            mkdir($dir, 0775, TRUE);
            chmod($dir, 0775);
        }

        $path = $dir . '/' . $module . '.' . $this->file_extension;
        return $path;
    }

    public function getDownloadFilePath()
    {
        return $this->getRenderFilePath();
    }

    public function incrementHitCounter() 
    {
        $Rendering = $this->_getRenderingRecord();
        $Rendering->hits ++;
        $Rendering->downloaded_at = date('Y-m-d H:i:s');
        $Rendering->save();
    }

    public static function getRenderBasePath() 
    {
        return dirname(__FILE__) . '/../../bibles/rendered/';
    }

    public static function getRenderBiblesLimit() 
    {
        return static::$render_bibles_limit;
    }

    public static function getName() 
    {
        return static::$name;
    }    

    public static function getDescription() 
    {
        return static::$description;
    }

    public static function getRendererId($settings = array()) 
    {
        $cl = explode('\\', get_called_class());
        $cl = array_pop($cl);
        return $cl;
    }
}

