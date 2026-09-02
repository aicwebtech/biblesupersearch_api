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
        $data = \DB::table($db_table)->get()->all();

        // An empty table still gets its header row, so the columns come from the schema instead.
        $fields = $data ? array_keys(get_object_vars($data[0])) : \Schema::getColumnListing($db_table);
        $fields = array_values(array_diff($fields, ['created_at', 'updated_at']));

        $handle = fopen($filepath, 'w');

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