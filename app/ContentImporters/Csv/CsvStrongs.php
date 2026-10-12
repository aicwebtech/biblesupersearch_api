<?php

namespace App\ContentImporters\Csv;

/**
 * Class CsvStrongs
 *
 * This class serves as a content importer for CSV files containing Strong's dictionaries.
 * It extends the CsvAbstract class and provides specific properties for this import source.
 */
class CsvStrongs extends CsvAbstract
{
    /**
     * The import source type (e.g., 'csv', etc.)
     *
     * @var string
     */
    protected $import_source = 'csv_strongs';

    /**
     * The import class name for the specified import source
     *
     * @var string|null
     */
    protected $import_class = \App\ContentImporters\Csv\CsvStrongs::class;

}