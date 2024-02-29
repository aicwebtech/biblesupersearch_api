<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Models\Shortcuts\En as ShortcutEn;
use App\Models\Shortcuts\ShortcutAbstract as ShortcutAbs;

class ShortcutTest extends TestCase
{
    public function testFindByEnteredName() {
        $class_name = ShortcutAbs::getClassNameByLanguage('en');
        $this->assertEquals('App\Models\Shortcuts\En', $class_name);
        
        // Name
        $SC = $class_name::findByEnteredName('New Testament');
        $this->assertInstanceOf('App\Models\Shortcuts\En', $SC);
        $this->assertEquals('Matthew - Revelation', $SC->reference);
        
        // Short1
        $SC = $class_name::findByEnteredName('History');
        $this->assertInstanceOf('App\Models\Shortcuts\En', $SC);
        $this->assertEquals('Joshua - Esther', $SC->reference);

        // Short2
        $SC = $class_name::findByEnteredName('NT');
        $this->assertInstanceOf('App\Models\Shortcuts\En', $SC);
        $this->assertEquals('Matthew - Revelation', $SC->reference);
        
        // Short3
        $SC = $class_name::findByEnteredName('OT');
        $this->assertInstanceOf('App\Models\Shortcuts\En', $SC);
        $this->assertEquals('Genesis - Malachi', $SC->reference);
    }
    
    // All of these will return false
    public function testFailToFindByEnteredName() {
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
