<?php

namespace App\ContentImporters;

/**
 * Class SpreadsheetAbstract 
 * 
 * This abstract class serves as a base for content importers that handle spreadsheet files, ie Excel and CSV files.
 * It extends the ContentImportAbstract class and provides common properties and methods for spreadsheet importers.
 * 
 * One of the key features of this class is the ability to handle mapping specified by the users in the UI. 
 */
abstract class SpreadsheetAbstract extends ContentImportAbstract
{
    /**
     * The import source type (e.g., 'csv', 'excel', etc.)
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