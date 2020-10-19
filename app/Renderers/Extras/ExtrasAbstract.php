<?php

namespace App\Renderers\Extras;

Use App\Models\Language;

class ExtrasAbstract {
    use \App\Traits\Error;

    protected $overwrite = FALSE;

    public function render($overwrite = FALSE) {
        // die('HERE');
        $this->overwrite = $overwrite;
        $this->_renderBibleBookLists();

        return TRUE;
    }

    protected function _renderBibleBookLists() {
        foreach( config('bss_table_languages.books') as $lang) {
            $Language = Language::findByCode($lang);

            if(!$Language) {
                throw new \StandardException('No language for code ' . $lang);
            }

            $this->_renderBibleBookListSingle($Language);
        }
    }

    protected function _renderBibleBookListSingle(Language $Language) {
        var_dump($Language->name);
    }
}