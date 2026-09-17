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

        static::removeStaleFile($filepath);

        $handle = fopen($filepath, 'w');

        if($handle === FALSE) {
            throw new \Exception('Unable to open extras file for writing: ' . $filepath);
        }

        $this->_writeCsvRow($handle, $fields, $filepath);

        foreach($data as $key => &$row) {
            $csv_row = [];

            foreach($fields as $f) {
                $csv_row[] = $row->$f;
            }

            $this->_writeCsvRow($handle, $csv_row, $filepath);
        }
        unset($row);

        // fclose() flushes what is still buffered, so a disk that filled mid-dump can
        // surface here rather than at any individual row.
        if(!fclose($handle)) {
            throw new \Exception('Unable to finish writing extras file: ' . $filepath);
        }

        return $filepath;
    }


    /**
     * Write one CSV row, failing loudly rather than silently dropping or truncating it.
     *
     * Delegates to the shared checked writer: fputcsv() alone cannot be verified, because
     * it reports a refused write as 0 and a short write as the count it managed rather
     * than as FALSE.
     *
     * @param  resource  $handle
     * @param  array     $row
     * @param  string    $filepath  For the error message only
     * @return void
     * @throws \RuntimeException
     */
    private function _writeCsvRow($handle, array $row, string $filepath): void
    {
        try {
            static::putCsvRowOrFail($handle, $row, $this->escape, $filepath);
        }
        catch(\RuntimeException $e) {
            fclose($handle);

            throw $e;
        }
    }
}