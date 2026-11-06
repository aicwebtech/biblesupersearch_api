<?php

namespace App\ContentImporters\Excel;

use App\Traits\ContentFormatters\StrongsFormatter;

/**
 * Class ExcelStrongs
 *
 * Imports a Strong's dictionary from an Excel spreadsheet.
 */
class ExcelStrongs extends ExcelAbstract
{
    use StrongsFormatter;
}
