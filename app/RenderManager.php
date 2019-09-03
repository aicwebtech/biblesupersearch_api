<?php

namespace App;
use App\Models\Bible;

class RenderManager {
    use Traits\Error;

    static public $register = [
        'pdf'       => \App\Renderers\PdfPrintable::class,
        'text'      => \App\Renderers\PlainText::class,
        'csv'       => \App\Renderers\Csv::class,
    ];

    protected $Bibles = [];
    protected $format = [];
    protected $zip = FALSE;
    protected $multi_bibles = FALSE;
    protected $multi_format = FALSE;

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
                if(!static::$register[$fm]) {
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

    public function render($overwrite = FALSE, $suppress_overwrite_error = TRUE) {
        if($this->hasErrors()) {
            return FALSE;
        }

        foreach($this->format as $format) {
            $CLASS = static::$register[$format];

            foreach($this->Bibles as $Bible) {
                $Renderer = new $CLASS($Bible);

                if(!$Renderer->render($overwrite, $suppress_overwrite_error)) {
                    $this->addErrors($Renderer->getErrors(), $Renderer->getErrorLevel());
                }
            }
        }

        return !$this->hasErrors();
    }

    public function download() {
        if($this->hasErrors()) {
            return FALSE;
        }

        $rs = $this->render();

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

    static public function cleanUp() {

    }

    static public function getRendererList() {
        $list = [];

        foreach(static::$register as $format => $CLASS) {
            $list[$format] = [
                'format' => $format,
                'name'   => $CLASS::$name,
                'desc'   => $CLASS::$description,
                'CLASS'  => $CLASS,
            ];
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
