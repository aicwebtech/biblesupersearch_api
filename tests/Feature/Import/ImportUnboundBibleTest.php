<?php

namespace Tests\Feature\Import;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use App\Engine;
use App\ImportManager;

class ImportUnboundBibleTest extends Base 
{
    protected $file_name = 'peshitta.zip';
    protected $importer = 'unbound';
}
