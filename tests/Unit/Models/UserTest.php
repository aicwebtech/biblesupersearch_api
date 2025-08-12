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
            ['name', 'username', 'email', 'password', 'user_access', 'comments'],
            $user->getFillable()
        );
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