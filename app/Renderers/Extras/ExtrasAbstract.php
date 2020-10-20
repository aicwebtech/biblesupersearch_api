<?php

namespace App\Renderers\Extras;

Use App\Models\Language;

class ExtrasAbstract {
    use \App\Traits\Error;

    protected $overwrite = FALSE;

    protected $fileinfo = [
        'books' => [
            'desc'  => 'Bible Book Lists',
            'items' => [],
        ],
        'misc' => [
            'desc'  => 'Miscellaneous',
            'items' => [],
        ]
    ];

    public function render($overwrite = FALSE) {
        $this->overwrite = $overwrite;
        $this->_renderBibleBookLists();
        $this->_renderBibleShortcuts();

        return $this->hasErrors() ? FALSE : TRUE;
    }

    public function getFileInfo() {
        return $this->fileinfo;
    }

    protected function _renderBibleBookLists() {
        foreach( config('bss_table_languages.books') as $lang) {
            $Language = Language::findByCode($lang);

            if(!$Language) {
                throw new \StandardException('No language for code ' . $lang);
            }

            $filepath = $this->_renderBibleBookListSingle($lang);
            $this->_pushFileInfo('books', $filepath, $Language->name);
        }
    }

    protected function _renderBibleBookListSingle($lang_code) {
        throw new \StandardException('Method Not Implemented!');
    }

    protected function _renderBibleShortcuts() {
        foreach( config('bss_table_languages.shortcuts') as $lang) {
            $Language = Language::findByCode($lang);

            if(!$Language) {
                throw new \StandardException('No language for code ' . $lang);
            }

            $filepath = $this->_renderBibleShortcutsSingle($lang);
            // $this->_pushFileInfo('shortcuts', $filepath, $Language->name); // future
            $this->_pushFileInfo('misc', $filepath, 'Shortcuts');
        }
    }

    protected function _renderBibleShortcutsSingle($lang_code) {
        throw new \StandardException('Method Not Implemented!');
    }

    protected function _pushFileInfo($list, $filepath, $filedesc) {
        if(!array_key_exists($list, $this->fileinfo) || !$filepath || !$list) {
            return FALSE;
        }

        $this->fileinfo[$list]['items'][] = [
            'desc' => $filedesc,
            'path' => $filepath,
            'file' => basename($filepath),
        ];
    }

    protected function _getDBDumpDir() {
        return dirname(__FILE__) . '/../../../database/dumps/';
    }

    public function getRenderFileDir($create_dir = TRUE) {
        if($this->hasErrors()) {
            return FALSE;
        }

        $renderer = (new \ReflectionClass($this))->getShortName();

        $dir = static::getRenderBasePath() . '/' . $renderer . '/';

        if(!is_dir($dir) && $create_dir) {
            mkdir($dir, 0775, TRUE);
            chmod($dir, 0775);
        }

        return $dir;
    }

    public static function getRenderBasePath() {
        return dirname(__FILE__) . '/../../../bibles/rendered/extras';
    }
}