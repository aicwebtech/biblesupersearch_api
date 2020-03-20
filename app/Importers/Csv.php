<?php

/**
 * Importer for CSV files
 */

namespace App\Importers;
use App\Models\Bible;
use ZipArchive;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;

class Csv extends SpreadsheetAbstract {
    
    public function checkUploadedFile(UploadedFile $File) {
        $file_data = file($File->getPathname());
        $tmp_data  = [];

        foreach($file_data as $key => $csv_row) {
            if($key < $this->settings['first_row_data']) {
                $tmp_data[] = [];
                continue;
            }
            
            $tmp_data[] = str_getcsv($csv_row); 

            if($key > 200) {
                break;
            }

        }

        return $this->_checkParsedFile($tmp_data);
    }
}