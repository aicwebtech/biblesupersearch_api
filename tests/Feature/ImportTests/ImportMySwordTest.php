<?php

namespace Tests\Feature\ImportTests;

// Something's wrong with the autoloader
// I shouldn't have to manually load this!
// Plus, it's in the same namespace
require_once(dirname(__FILE__) . '/Base.php'); 

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use App\Engine;
use App\ImportManager;

class ImportMySwordTest extends Base 
{
    protected $file_name = 'mysword.bbl.mybible.gz';
    protected $importer = 'mysword';
}
