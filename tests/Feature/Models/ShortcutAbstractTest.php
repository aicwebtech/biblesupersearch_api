<?php

namespace Tests\Feature\Models;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;

use App\Models\Shortcuts\En as ShortcutEn;
use App\Models\Shortcuts\ShortcutAbstract as ShortcutAbs;

class ShortcutAbstractTest extends TestCase
{
    #[DataProvider('findByEnteredNameDataProvider')]
    public function testFindByEnteredName(string $name, string $reference) 
    {
        $class_name = ShortcutAbs::getClassNameByLanguage('en');
        $this->assertEquals('App\Models\Shortcuts\En', $class_name);

        $SC = $class_name::findByEnteredName($name);
        $this->assertInstanceOf(ShortcutEn::class, $SC);
        $this->assertInstanceOf(ShortcutAbs::class, $SC);
        $this->assertEquals($reference, $SC->reference);
    }

    public static function findByEnteredNameDataProvider()
    {
        return [
            ['New Testament' , 'Matthew - Revelation'],
            ['History'       , 'Joshua - Esther'],
            ['NT'            , 'Matthew - Revelation'],
            ['OT'            , 'Genesis - Malachi'],
        ];
    }
    
    // All of these will return false
    public function testFailToFindByEnteredName() 
    {
        $SC = ShortcutEn::findByEnteredName('Old Test'); // Only exact matches
        $this->assertFalse($SC);
        $SC = ShortcutEn::findByEnteredName('No Such Shortcut 1234'); // No match
        $this->assertFalse($SC);
        $SC = ShortcutEn::findByEnteredName(NULL); //
        $this->assertFalse($SC);
        $SC = ShortcutEn::findByEnteredName(FALSE); //
        $this->assertFalse($SC);
        $SC = ShortcutEn::findByEnteredName(''); //
        $this->assertFalse($SC);
    }
}
