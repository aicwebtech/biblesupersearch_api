<?php

namespace App\Renderers\Extras;

// :todo finish this class!!

// Simply copies existing CSV files to the output .ZIP file

class Csv extends ExtrasAbstract 
{
    
    protected function _renderBibleBookListSingle($lang_code) 
    {
        $filename = 'bible_books/' . $lang_code . '.csv';
        
        return $this->_getDBDumpDir() . $filename;
    }

    protected function _renderBibleShortcuts() 
    {
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

    protected function _renderStrongsDefinitionsHelper() 
    {
        return $this->_getDBDumpDir() . 'strongs_definitions.csv';
    }

    protected function _renderLanguagesHelper() 
    {
        return $this->_getDBDumpDir() . 'languages.csv';
    }

}