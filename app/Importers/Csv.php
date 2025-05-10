<?php

/**
 * Importer for CSV files
 */

namespace App\Importers;
use App\Models\Bible;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;

class Csv extends SpreadsheetAbstract 
{
    protected $escape = "\\";

    public function checkUploadedFile(UploadedFile $File): bool  
    {
        $file_data = file($File->getPathname());

        if(!$file_data) {
            return $this->addError('Could not open file');
        }

        $tmp_data  = [];

        foreach($file_data as $key => $csv_row) {
            if($key < $this->first_row_data) {
                continue;
            }
            
            $tmp_data[] = str_getcsv($csv_row, escape: $this->escape); 

            if($key > 200) {
                break;
            }
        }

        return $this->_checkParsedFile($tmp_data);
    }

    protected function _importFromSpreadsheet($file_path) 
    {
        $file_data = file($file_path);

        if(!$file_data) {
            return $this->addError('Could not open file');
        }

        foreach($file_data as $key => $row) {
            if($key < $this->first_row_data) {
                continue;
            }
            
            $row = str_getcsv($row, escape: $this->escape); 
            $m = $this->_mapSpreadsheetRow($row);
            $this->_addVerse($m['book'], $m['chapter'], $m['verse'], $m['text']);
        }

        $this->_insertVerses();
        return TRUE;
    }
}