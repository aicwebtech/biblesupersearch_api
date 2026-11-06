<?php

namespace App\Importers;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Restricts a spreadsheet read to the first N rows, or to rows $start_row - N.
 *
 * PhpSpreadsheet's load() materializes a Cell object for every cell in the workbook. When only
 * the leading rows are needed - as when validating an upload's column mapping - that is orders
 * of magnitude more work than required, so this filter keeps the reader from building the rest.
 */
class RowLimitReadFilter implements IReadFilter
{
    /**
     * @param int $row_limit Last row read
     * @param int $start_row First row read, for reading a sheet in chunks
     */
    public function __construct(protected int $row_limit, protected int $start_row = 1) { }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $row >= $this->start_row && $row <= $this->row_limit;
    }
}
