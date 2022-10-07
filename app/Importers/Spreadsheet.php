<?php

/**
 * Importer for spreadsheet files, including Excel and OpenDocument
 */

namespace App\Importers;
use App\Models\Bible;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet as PhpSpreadsheet;

class Spreadsheet extends SpreadsheetAbstract {
    protected $Spreadsheet;

    public function checkUploadedFile(UploadedFile $File) {
        if(!$this->_openSpreadsheetFile($File->getPathname())) {
            return FALSE;
        }

        $file_data = $this->_readSpreadsheet(200);
        $tmp_data  = [];

        foreach($file_data as $key => $row) {
            $tmp_data[] = $row;

            if($key > 200) {
                break;
            }
        }

        return $this->_checkParsedFile($tmp_data);
    }

    protected function _importFromSpreadsheet($file_path) {
        if(!$this->_openSpreadsheetFile($file_path)) {
            return FALSE;
        }

        $file_data = $this->_readSpreadsheet();

        foreach($file_data as $key => $row) {
            $m = $this->_mapSpreadsheetRow($row);
            $this->_addVerse($m['book'], $m['chapter'], $m['verse'], $m['text']);
        }

        $this->_insertVerses();

        return TRUE;
    }

    protected function _openSpreadsheetFile($file_path) {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_path);
        }
        catch (Reader\Exception $e) {
            return $this->addError('Could not open file, not a known spreadsheet format.');
        }

        $reader->setReadDataOnly(TRUE);
        $this->Spreadsheet = $reader->load($file_path);
        return TRUE;
    }

    protected function _readSpreadsheet($row_limit = NULL) {
        if(!$this->Spreadsheet) {
            return FALSE;
        }

        $Sheet  = $this->Spreadsheet->getActiveSheet();
        $maxCol = count($this->column_map);
        $maxCol = chr($maxCol + 64);

        $maxRow = $row_limit ?: $Sheet->getHighestRow();
        $minRow = $this->first_row_data + 1;
        $range  = 'A' . $minRow .':' . $maxCol . $maxRow;

        return $Sheet->rangeToArray($range, NULL, FALSE, FALSE);
    }
}
