<?php

namespace App\Importers;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Restricts a spreadsheet read to the first N rows.
 *
 * PhpSpreadsheet's load() materializes a Cell object for every cell in the workbook. When only
 * the leading rows are needed - as when validating an upload's column mapping - that is orders
 * of magnitude more work than required, so this filter keeps the reader from building the rest.
 */
class RowLimitReadFilter implements IReadFilter
{
    public function __construct(protected int $row_limit) { }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $row <= $this->row_limit;
    }
}
