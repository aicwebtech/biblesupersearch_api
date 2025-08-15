<?php

namespace App\Renderers\Extras;

use App\Models\Books\BookAbstract AS Book;

class Json extends ExtrasAbstract 
{
    
    protected function _renderBibleBookListSingle($lang_code) 
    {
        $table = 'books_' . $lang_code;
        
        $filepath = $this->getRenderFileDir() . 'books_' . $lang_code . '.json';
        $this->_dumpJsonGeneric($table, $filepath);
        return $filepath;
    }

    protected function _renderBibleShortcutsSingle($lang_code) 
    {
        $table = 'shortcuts_' . $lang_code;
        
        $filepath = $this->getRenderFileDir() . 'shortcuts_' . $lang_code . '.json';
        $this->_dumpJsonGeneric($table, $filepath);
        return $filepath;
    }

    protected function _renderStrongsDefinitionsHelper() 
    {
        $filepath = $this->getRenderFileDir() . 'strongs_definitions.json';
        $this->_dumpJsonGeneric('strongs_definitions', $filepath);
        return $filepath;
    }

    protected function _renderLanguagesHelper() 
    {
        $filepath = $this->getRenderFileDir() . 'languages.json';
        $this->_dumpJsonGeneric('languages', $filepath);
        return $filepath;
    }

    private function _dumpJsonGeneric($db_table, $filepath) 
    {
        $db_table = env('DB_PREFIX') . $db_table;
        $ignore_fields = ['created_at', 'updated_at'];

        $data   = \DB::select("SELECT * FROM {$db_table}");

        foreach($data as $key => &$row) {
            foreach($ignore_fields as $f) {
                if(property_exists($row, $f)) {
                    unset($row->$f);
                }
            }
        }
        unset($row);

        file_put_contents($filepath, json_encode($data));
        return $filepath;
    }
}