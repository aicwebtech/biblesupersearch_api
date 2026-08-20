<?php

/**
 * Importer for spreadsheet files, including Excel and OpenDocument
 */

namespace App\Importers;
use App\Models\Bible;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet as PhpSpreadsheet;

class Spreadsheet extends SpreadsheetAbstract 
{
    /** Rows inspected when validating an uploaded file's column mapping. */
    const CHECK_ROW_LIMIT = 200;

    protected $Spreadsheet;

    public function checkUploadedFile(UploadedFile $File): bool 
    {
        set_time_limit(300);
    
        if(!$this->_openSpreadsheetFile($File->getPathname(), static::CHECK_ROW_LIMIT)) {
            return FALSE;
        }

        $file_data = $this->_readSpreadsheet(static::CHECK_ROW_LIMIT);
        $tmp_data  = [];

        foreach($file_data as $key => $row) {
            $tmp_data[] = $row;

            if($key > static::CHECK_ROW_LIMIT) {
                break;
            }
        }

        return $this->_checkParsedFile($tmp_data);
    }

    protected function _importFromSpreadsheet($file_path) 
    {
        set_time_limit(300);
    
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

    /**
     * @param string $file_path
     * @param int|null $row_limit Stop the reader after this many rows. Only pass this when the
     *                            caller genuinely needs a prefix of the sheet - load() otherwise
     *                            builds a Cell object for every cell in the workbook.
     */
    protected function _openSpreadsheetFile($file_path, $row_limit = NULL) 
    {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_path);
        }
        catch (Reader\Exception $e) {
            return $this->addError('Could not open file, not a known spreadsheet format.');
        }

        $reader->setReadDataOnly(TRUE);

        if($row_limit) {
            $reader->setReadFilter(new RowLimitReadFilter((int) $row_limit));
        }

        $this->Spreadsheet = $reader->load($file_path);
        return TRUE;
    }

    protected function _readSpreadsheet($row_limit = NULL) 
    {
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
