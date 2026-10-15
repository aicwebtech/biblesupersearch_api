<?php

namespace App\ContentImporters\Excel;

use App\ContentImporters\SpreadsheetAbstract;
use App\Importers\RowLimitReadFilter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;

/**
 * Class ExcelAbstract
 *
 * Base for content importers that read spreadsheet files with PhpSpreadsheet.  It can read any
 * format PhpSpreadsheet does, but is only offered for Excel (.xls, .xlsx).
 *
 * Reads the first worksheet in chunks of rows: load() builds a Cell object for every cell it
 * reads, so loading a large sheet in one go would exhaust memory.
 */
abstract class ExcelAbstract extends SpreadsheetAbstract
{
    /**
     * Rows read per batch.  Each batch re-parses the whole file, so batches are much larger
     * than for CSV: for a 14k row Strong's .xlsx, 5000 reads in 25s / 48MB against 31s / 33MB
     * at 2000.
     */
    public const BATCH_SIZE = 5000;

    protected ?IReader $Reader = NULL;
    protected ?string $path = NULL;
    protected int $total_rows = 0;

    protected function openFile(string $path): bool
    {
        try {
            $Reader = IOFactory::createReaderForFile($path);
            $Reader->setReadDataOnly(TRUE);

            $info = $Reader->listWorksheetInfo($path);

            if(empty($info)) {
                return $this->addError('Could not open file, it has no worksheets.', 4);
            }

            $Reader->setLoadSheetsOnly($info[0]['worksheetName']);
        }
        catch(\Throwable $e) {
            return $this->addError('Could not open file, not a known spreadsheet format.', 4);
        }

        $this->Reader     = $Reader;
        $this->path       = $path;
        $this->total_rows = (int) $info[0]['totalRows'];
        return TRUE;
    }

    protected function readBatches(int $size): \Generator
    {
        if(!$this->Reader) {
            return;
        }

        $last_column = static::numberToColumn($this->getColumnCount());

        for($start = $this->first_row_data; $start <= $this->total_rows; $start += $size) {
            $end = min($start + $size - 1, $this->total_rows);

            $this->Reader->setReadFilter(new RowLimitReadFilter($end, $start));
            $Spreadsheet = $this->Reader->load($this->path);

            $data = $Spreadsheet->getSheet(0)->rangeToArray('A' . $start . ':' . $last_column . $end, NULL, FALSE, FALSE);
            $Spreadsheet->disconnectWorksheets();
            unset($Spreadsheet);

            $batch = [];

            foreach(array_values($data) as $offset => $row) {
                if(array_filter($row, fn($value) => $value !== NULL && $value !== '')) {
                    $batch[$start + $offset] = $row;
                }
            }

            if($batch) {
                yield $batch;
            }
        }
    }

    protected function closeFile(): void
    {
        $this->Reader = NULL;
        $this->path   = NULL;
        $this->total_rows = 0;
    }
}
