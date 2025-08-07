<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use PHPUnit\Framework\TestCase;

use App\Models\Language;

class LanguageTest extends TestCase
{
    public function testInstance() 
    {
        $language = new Language();
        $this->assertInstanceOf(Language::class, $language);
    }
}
