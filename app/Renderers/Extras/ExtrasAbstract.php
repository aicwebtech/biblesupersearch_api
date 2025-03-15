<?php

namespace App\Renderers\Extras;

Use App\Models\Language;

class ExtrasAbstract 
{
    use \App\Traits\Error;

    protected $overwrite = FALSE;
    protected $filelist = [];

    protected $fileinfo = [
        'books' => [
            'desc'  => 'Bible Book Lists',
            'items' => [],
        ],
        'misc' => [
            'desc'  => 'Miscellaneous',
            'items' => [],
        ],
    ];

    public function render($overwrite = FALSE) {
        $this->overwrite = TRUE; //$overwrite;
        $this->_renderBibleBookLists();
        $this->_renderLanguages();
        $this->_renderBibleShortcuts();
        $this->_renderStrongsDefinitions();

        $this->_renderReadme();

        return $this->hasErrors() ? FALSE : TRUE;
    }

    public function getFileInfo() {
        return $this->fileinfo;
    }    

    public function getFileList() {
        return $this->filelist;
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
            $this->_pushFileInfo('misc', $filepath, 'Bible Search Shortcuts');
        }
    }

    protected function _renderBibleShortcutsSingle($lang_code) {
        throw new \StandardException('Method Not Implemented!');
    }

    protected function _renderStrongsDefinitions() {
        $filepath = $this->_renderStrongsDefinitionsHelper();
        $this->_pushFileInfo('misc', $filepath, 'Strong\'s Definitions');
    }

    protected function _renderStrongsDefinitionsHelper() {
        throw new \StandardException('Method Not Implemented!');
    }

    protected function _renderLanguages() {
        $filepath = $this->_renderLanguagesHelper();
        $this->_pushFileInfo('misc', $filepath, 'Languages');
    }

    protected function _renderLanguagesHelper() {
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

        $this->filelist[] = $filepath;
    }

    protected function _getDBDumpDir() {
        return dirname(__FILE__) . '/../../../database/dumps/';
    }

    protected function _renderReadme() {
        $filepath = $this->getRenderFileDir() . 'readme.txt';
        $readme = 'Bible SuperSearch Extras';

        foreach($this->fileinfo as $group) {
            $readme .= "\n\n";
            $readme .= $group['desc'] . "\n\n";

            foreach($group['items'] as $file) {
                $readme .= str_pad($file['file'] . ' ', 30, '-') . ' ' . $file['desc'] . "\n"; 
            }
        } 

        file_put_contents($filepath, $readme);
        $this->filelist[] = $filepath;
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