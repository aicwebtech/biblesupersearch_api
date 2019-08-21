<?php

namespace App;
use Models\Bible;

class RenderManager {
    use Traits\Error;

    static public $register = [
        'text'      => \App\Renderers\PlainText::class,
        'pdf'       => \App\Renderers\PdfPrintable::class,
    ];

    protected $Bibles = [];
    protected $format = [];
    protected $zip = FALSE;
    protected $multi_bibles = FALSE;
    protected $multi_format = FALSE;

    public function __construct($modules, $format, $zip = FALSE) {
        $this->multi_bibles = ($modules == 'ALL' || count($modules) > 2) ? TRUE : FALSE;
        $this->multi_format = ($format  == 'ALL' || count($format)  > 2) ? TRUE : FALSE;
        $this->zip = ($this->multi_bibles && $this->multi_format) ? TRUE : $zip;

        if($this->multi_bibles && $this->multi_format) {
            $this->addError('Cannot request multiple items for both Bible and format!');
            return;
        }

        if($format == 'ALL') {
            $this->format = array_keys(static::$register);
        }
        else {
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

    public function render() {
        if($this->hasErrors()) {
            return FALSE;
        }


    }

    public function download() {
        if($this->hasErrors()) {
            return FALSE;
        }


    }

    static public function getRendererList() {
        $list = [];

        foreach(static::$register as $format => $CLASS) {
            $list[$format] = [
                'format' => $format,
                'name'   => $CLASS::$name,
                'desc'   => $CLASS::$description,
            ];
        }

        return $list;
    }
}
