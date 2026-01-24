<?php

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use \App\Traits\Input;

class InputTest extends TestCase
{
    private function newSubject()
    {
        return new class 
        {
            use Input;
        };
    }

    public function testIsTruthyReturnsFalseWhenKeyMissing()
    {
        $obj = $this->newSubject();
        $this->assertFalse($obj->isTruthy('missing', []));
    }

    public function testIsTruthyReturnsFalseForStringFalse()
    {
        $obj = $this->newSubject();
        $this->assertFalse($obj->isTruthy('flag', ['flag' => 'false']));
    }

    public function testIsTruthyReturnsFalseForBooleanFalse()
    {
        $obj = $this->newSubject();
        $this->assertFalse($obj->isTruthy('flag', ['flag' => false]));
    }

    public function testIsTruthyReturnsTrueForBooleanTrueAndStringTrue()
    {
        $obj = $this->newSubject();
        $this->assertTrue($obj->isTruthy('t1', ['t1' => true]));
        $this->assertTrue($obj->isTruthy('t2', ['t2' => 'true']));
    }

    public function testIsTruthyTreatsZeroAsTruthy()
    {
        $obj = $this->newSubject();
        $this->assertTrue($obj->isTruthy('zero', ['zero' => 0]));
    }

    public function testDefaultValueReturnsValueWhenPresent()
    {
        $obj = $this->newSubject();
        $this->assertSame('value', $obj->defaultValue('key', ['key' => 'value'], 'default'));
    }

    public function testDefaultValueReturnsDefaultWhenMissing()
    {
        $obj = $this->newSubject();
        $this->assertSame('default', $obj->defaultValue('key', [], 'default'));
    }

    public function testDefaultValueReturnsNullWhenDefaultNotProvided()
    {
        $obj = $this->newSubject();
        $this->assertNull($obj->defaultValue('key', []));
    }

    public function testDefaultValueReturnsNullWhenKeyExistsWithNull()
    {
        $obj = $this->newSubject();
        $this->assertNull($obj->defaultValue('key', ['key' => null], 'default'));
    }
}