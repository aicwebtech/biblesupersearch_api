<?php

namespace App\ContentImporters;

/**
 * Class SqliteAbstract 
 * 
 * FOR FUTURE USE: This class is intended to be a base class for content importers that handle SQLite database files.
 *
 * This abstract class serves as a base for content importers that handle SQLite database files.
 * It extends the ContentImportAbstract class and provides common properties and methods for SQLite importers.
 */

class SqliteAbstract extends ContentImportAbstract
{
    /**
     * The import source type (e.g., 'sqlite', etc.)
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