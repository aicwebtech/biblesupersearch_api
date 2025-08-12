<?php

namespace Tests\Feature\Import;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use App\Engine;
use App\ImportManager;

class ImportBibleAnalyzerTest extends Base 
{
    protected $file_name = 'wycliff.bib';
    protected $importer = 'analyzer';
}
