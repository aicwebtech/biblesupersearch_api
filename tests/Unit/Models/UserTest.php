<?php

namespace Tests\Unit\Models;

use App\User;
use App\Notifications\CustomPasswordReset;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testTableName()
    {
        $user = new User();
        $this->assertEquals('users', $user->getTable());
    }

    public function testFillableAttributes()
    {
        $user = new User();
        $this->assertEquals(
            ['name', 'username', 'email', 'password', 'comments'],
            $user->getFillable()
        );
    }

    /**
     * The privilege field driving the auth:N middleware must never be
     * mass-assignable, otherwise a crafted request could escalate privileges.
     */
    public function testAccessLevelIsNotMassAssignable()
    {
        $user = new User();
        $this->assertNotContains('access_level', $user->getFillable());

        $user->fill(['access_level' => 100]);
        $this->assertNull($user->access_level, 'access_level must be ignored by mass assignment');
    }

    public function testHiddenAttributes()
    {
        $user = new User();
        $this->assertEquals(['password', 'remember_token'], $user->getHidden());
    }

    public function testGetAuthPasswordNameReturnsPassword()
    {
        $user = new User();
        $this->assertEquals('password', $user->getAuthPasswordName());
    }

}