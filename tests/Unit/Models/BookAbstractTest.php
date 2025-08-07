<?php

namespace Tests\Unit\Models;

use App\Models\Books\BookAbstract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

class BookAbstractTest extends TestCase
{
    public function testGetClassNameByLanguageRaw()
    {
        $class = BookAbstract::getClassNameByLanguageRaw('en');
        $this->assertEquals('App\Models\Books\En', $class);
    }

    // :todo mock this
    // public function testGetClassNameByLanguageReturnsDefaultIfNotExists()
    // {
    //     // Simulate config('app.locale') returns 'en'
    //     $default = 'App\Models\Books\En';
    //     $this->assertEquals($default, BookAbstract::getClassNameByLanguage('notexist', false));
    // }

    public function testGetClassNameByLanguageStrictReturnsFalseIfNotExists()
    {
        $this->assertFalse(BookAbstract::getClassNameByLanguageStrict('notexist', false));
    }

    public function testGetLanguageReturnsClassNameLowercase()
    {
        $this->assertEquals('app\models\books\bookabstract', BookAbstract::getLanguage());
    }

    public function testGetCsvFileName()
    {
        $this->assertEquals('bible_books/en.csv', BookAbstract::getCsvFileName('en'));
    }

    // :todo mock this
    // public function testIsSupportedLanguage()
    // {
    //     // This will call \App\Models\Language::hasBookSupport, which should be mocked in a real test
    //     $this->assertIsBool(BookAbstract::isSupportedLanguage('en'));
    // }

    public function testGetSupportedLanguagesContainsEn()
    {
        $langs = BookAbstract::getSupportedLanguages();
        $this->assertContains('en', $langs);
    }

}