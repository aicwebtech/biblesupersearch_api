<?php

namespace App\ContentImporters;

abstract class ContentImportAbstract
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