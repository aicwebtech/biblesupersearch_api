<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\User;

class UserTest extends TestCase
{
    public function testOneUser() {

        $users = DB::table('users')->get();

        // Make sure there is at leste ONE user in the system
        $this->assertGreaterThanOrEqual(1, count($users));
    }
}
