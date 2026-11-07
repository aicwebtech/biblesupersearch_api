<?php

namespace App\ContentImporters\Csv;

use App\Traits\ContentFormatters\StrongsFormatter;

/**
 * Class CsvStrongs
 *
 * Imports a Strong's dictionary from a CSV file.
 */
class CsvStrongs extends CsvAbstract
{
    use StrongsFormatter;
}
