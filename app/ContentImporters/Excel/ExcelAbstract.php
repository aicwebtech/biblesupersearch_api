<?php

namespace App\ContentImporters\Excel;

use App\ContentImporters\SpreadsheetAbstract;
use App\Importers\RowLimitReadFilter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Class ExcelAbstract
 *
 * Base for content importers that read spreadsheet files with PhpSpreadsheet.  It can read any
 * format PhpSpreadsheet does, but is only offered for Excel (.xls, .xlsx).
 *
 * Reads the first worksheet in chunks of rows: load() builds a Cell object for every cell it
 * reads, so loading a large sheet in one go would exhaust memory.
 *
 * Formula cells are read as the result Excel / LibreOffice saved with the file; they are not
 * recalculated.  See useSavedFormulaResults().
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

            $Sheet = $Spreadsheet->getSheet(0);
            $data  = $Sheet->rangeToArray('A' . $start . ':' . $last_column . $end, NULL, FALSE, FALSE);
            $data  = $this->useSavedFormulaResults($Sheet, array_values($data), $start);
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

    /**
     * Replaces each formula with the result saved in the file.
     *
     * Excel and LibreOffice save a formula's last result alongside it, so nothing is
     * recalculated: recalculating would need the whole workbook in memory (a formula can
     * reference any row, or another sheet), not one chunk of rows.  A formula without a
     * saved result (a file written by a script that never calculated it) is left as is, for
     * mapRow() to refuse.
     *
     * @param Worksheet $Sheet
     * @param array<int, array<int, mixed>> $data Rows from rangeToArray(), from row $start
     * @param int $start Spreadsheet row of $data[0]
     * @return array<int, array<int, mixed>>
     */
    protected function useSavedFormulaResults(Worksheet $Sheet, array $data, int $start): array
    {
        foreach($data as $offset => $row) {
            foreach($row as $index => $value) {
                if(!is_string($value) || !str_starts_with($value, '=')) {
                    continue;
                }

                $Cell = $Sheet->getCell(static::numberToColumn($index + 1) . ($start + $offset));

                if($Cell->getDataType() !== DataType::TYPE_FORMULA) {
                    continue;
                }

                $saved = $Cell->getOldCalculatedValue();

                if($saved !== NULL) {
                    $data[$offset][$index] = is_bool($saved) ? (int) $saved : $saved;
                }
            }
        }

        return $data;
    }

    protected function closeFile(): void
    {
        $this->Reader = NULL;
        $this->path   = NULL;
        $this->total_rows = 0;
    }
}
