<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Shortcuts\ShortcutAbstract;
use Illuminate\Database\Eloquent\Model;
use Mockery;

class ShortcutAbstractTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetClassNameByLanguage()
    {
        $className = ShortcutAbstract::getClassNameByLanguage('en');
        $this->assertEquals('App\Models\Shortcuts\En', $className);

        $className = ShortcutAbstract::getClassNameByLanguage('es');
        $this->assertEquals('App\Models\Shortcuts\Es', $className);
    }

    public function testFindByEnteredNameReturnsFalseIfNameEmpty()
    {
        $result = ShortcutAbstract::findByEnteredName('');
        $this->assertFalse($result);

        $result = ShortcutAbstract::findByEnteredName(null);
        $this->assertFalse($result);
    }

    public function testFindByEnteredNameWithIntCallsFind()
    {
        $mockClass = Mockery::mock('alias:App\Models\Shortcuts\English');
        $mockClass->shouldReceive('find')->with(123)->andReturn('found');
        $result = ShortcutAbstract::findByEnteredName(123, 'english');
        $this->assertEquals('found', $result);
    }

    public function testFindByEnteredNameWithStringFindsByName()
    {
        $mockClass = Mockery::mock('alias:App\Models\Shortcuts\English');
        $query = Mockery::mock();
        $mockClass->shouldReceive('where')->with('name', 'test')->andReturn($query);
        $query->shouldReceive('orwhere')->with('short1', 'test')->andReturnSelf();
        $query->shouldReceive('orwhere')->with('short2', 'test')->andReturnSelf();
        $query->shouldReceive('orwhere')->with('short3', 'test')->andReturnSelf();
        $query->shouldReceive('first')->andReturn('shortcut');

        $result = ShortcutAbstract::findByEnteredName('test', 'english');
        $this->assertEquals('shortcut', $result);
    }

    public function testFindByEnteredNameReturnsFalseIfNotFound()
    {
        $mockClass = Mockery::mock('alias:App\Models\Shortcuts\English');
        $query = Mockery::mock();
        $mockClass->shouldReceive('where')->with('name', 'notfound')->andReturn($query);
        $query->shouldReceive('orwhere')->andReturnSelf();
        $query->shouldReceive('first')->andReturn(null);

        $result = ShortcutAbstract::findByEnteredName('notfound', 'english');
        $this->assertFalse($result);
    }
}