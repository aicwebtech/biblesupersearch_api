<?php

namespace App\ContentImporters\Csv;

/**
 * Class CsvAbstract 
 * 
 * This abstract class serves as a base for content importers that handle CSV files.
 * It extends the SpreadsheetAbstract class and provides common properties and methods for CSV importers.
 */

abstract class CsvAbstract extends \App\ContentImporters\SpreadsheetAbstract
{
    /**
     * The import source type (e.g., 'csv', etc.)
     *
     * @var string
     */
    protected $import_source;

    /**
     * The import class name for the specified import source
     *
     * @var string|null
     */
    protected $import_class;

}