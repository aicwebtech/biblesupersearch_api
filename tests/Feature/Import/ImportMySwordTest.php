<?php

namespace Tests\Feature\Import;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use App\Engine;
use App\ImportManager;

class ImportMySwordTest extends Base 
{
    protected $file_name = 'gerben.bbl.mybible.gz';
    protected $importer = 'mysword';
}
