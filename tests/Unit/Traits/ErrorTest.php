<?php

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use \App\Traits\Error;

class ErrorTest extends TestCase
{
    // Minimal dummy class that uses the trait under test
    public function makeDummy()
    {
        return new class 
        {
            use Error;
        };
    }

    public function testInitialState()
    {
        $d = $this->makeDummy();
        $this->assertFalse($d->hasErrors());
        $this->assertSame([], $d->getErrors());
        $this->assertSame(200, $d->getHttpStatus());
        $this->assertSame(0, $d->getErrorLevel());
    }

    public function testAddErrorDefaultsAndLevel()
    {
        $d = $this->makeDummy();
        $ret = $d->addError('First error'); // default level 1, default http status from trait is 401
        $this->assertFalse($ret);
        $this->assertTrue($d->hasErrors());
        $this->assertSame(['First error'], $d->getErrors());
        $this->assertSame(401, $d->getHttpStatus());
        $this->assertSame(1, $d->getErrorLevel());

        // setErrorLevel return values
        $this->assertTrue($d->setErrorLevel(0));
        $this->assertSame(0, $d->getErrorLevel());
        $this->assertFalse($d->setErrorLevel(3));
        $this->assertSame(3, $d->getErrorLevel());
    }

    public function testAddErrorUniqueStringKey()
    {
        $d = $this->makeDummy();
        $d->addError('Message A', 1, null, 'keyA');
        $d->addError('Message A - ignored', 1, null, 'keyA'); // should not overwrite keyA
        $vals = $d->getErrors();
        $this->assertCount(1, $vals);
        $this->assertSame(['Message A'], $vals);
    }

    public function testAddErrorDuplicateMessageBooleanUnique()
    {
        $d = $this->makeDummy();
        $d->addError('dup');
        $d->addError('dup'); // second should be ignored due to in_array check
        $this->assertSame(['dup'], $d->getErrors());
    }

    public function testAddErrorsBulkAndHttpStatus()
    {
        $d = $this->makeDummy();
        $d->addErrors(['err1', 'err2'], 2, 400);
        $this->assertTrue($d->hasErrors());
        $this->assertSame(2, $d->getErrorLevel());
        $this->assertSame(400, $d->getHttpStatus());
        $this->assertEqualsCanonicalizing(['err1', 'err2'], $d->getErrors());
    }

    public function testResetErrors()
    {
        $d = $this->makeDummy();
        $d->addError('will be reset', 4, 500);
        $this->assertTrue($d->hasErrors());
        $d->resetErrors();
        $this->assertFalse($d->hasErrors());
        $this->assertSame([], $d->getErrors());
        $this->assertSame(0, $d->getErrorLevel());
        $this->assertSame(200, $d->getHttpStatus());
    }

    public function testAddErrorByHttpStatus()
    {
        $d = $this->makeDummy();
        $ret = $d->addErrorByHttpStatus(404);
        $this->assertFalse($ret);
        $this->assertTrue($d->hasErrors());
        $this->assertSame(404, $d->getHttpStatus());
        $this->assertContains('Not Found', $d->getErrors());
        // level for 404 should be set to 5 by implementation
        $this->assertSame(5, $d->getErrorLevel());

        // adding 200 should be treated as non-error and return FALSE without changing state
        $beforeCount = count($d->getErrors());
        $this->assertFalse($d->addErrorByHttpStatus(200));
        $this->assertCount($beforeCount, $d->getErrors());
    }
}