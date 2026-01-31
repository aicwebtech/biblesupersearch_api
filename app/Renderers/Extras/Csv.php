<?php

namespace App\Renderers\Extras;

// This mainly copies existing CSV files to the output .ZIP file

class Csv extends ExtrasAbstract 
{
    
    protected $escape = "\\";  // :todo: this should be a setting or default to ""

    protected function _renderBibleBookListSingle($lang_code) 
    {
        $filename = 'bible_books/' . $lang_code . '.csv';

        return $this->_copyDbDumpFileToRendered($filename, 'books_' . $lang_code . '.csv');
    }

    protected function _renderBibleShortcutsSingle($lang_code) 
    {
        return $this->_dumpCsvGeneric('shortcuts_' . $lang_code, $this->getRenderFileDir() . 'shortcuts_' . $lang_code . '.csv');
    }

    protected function _renderStrongsDefinitionsHelper() 
    {
        return $this->_copyDbDumpFileToRendered('strongs_definitions.csv');
    }

    protected function _renderLanguagesHelper() 
    {
        return $this->_copyDbDumpFileToRendered('languages.csv');
    }

    private function _dumpCsvGeneric($db_table, $filepath)
    {
        $db_table = env('DB_PREFIX') . $db_table;
        $data = \DB::select("SELECT * FROM {$db_table}");

        $handle = fopen($filepath, 'w');

        $fields = get_object_vars($data[0]);
        unset($fields['created_at']);
        unset($fields['updated_at']);
        
        $fields = array_keys($fields);

        fputcsv($handle, $fields, escape: $this->escape);

        foreach($data as $key => &$row) {
            $csv_row = [];

            foreach($fields as $f) {
                $csv_row[] = $row->$f;
            }

            fputcsv($handle, $csv_row, escape: $this->escape);
        }
        unset($row);
        
        fclose($handle);

        return $filepath;
    }

}