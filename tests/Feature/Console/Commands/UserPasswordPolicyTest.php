<?php

namespace Tests\Feature\Console\Commands;

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/*
 * The user:create and user:password CLI commands must enforce the same
 * password policy as the web paths (App\Providers\AppServiceProvider via
 * Password::defaults()): min(10) with mixed case, numbers, and symbols.
 */
class UserPasswordPolicyTest extends TestCase
{
    use DatabaseTransactions;

    public function testUserCreateRejectsWeakPassword()
    {
        $this->artisan('user:create', [
            'email_address' => 'weakcli@example.com',
            'username'      => 'weakcli',
            'password'      => 'password123',
        ])->assertFailed();

        $this->assertNull(User::where('username', 'weakcli')->first());
    }

    public function testUserCreateAcceptsStrongPassword()
    {
        $this->artisan('user:create', [
            'email_address' => 'strongcli@example.com',
            'username'      => 'strongcli',
            'password'      => 'Str0ng!Passw0rd',
        ])->assertSuccessful();

        $this->assertNotNull(User::where('username', 'strongcli')->first());
    }

    public function testUserPasswordRejectsWeakPassword()
    {
        $User = new User;
        $User->name     = 'PW CLI';
        $User->username = 'pwcli';
        $User->email    = 'pwcli@example.com';
        $User->password = bcrypt('Str0ng!Passw0rd');
        $User->save();

        $this->artisan('user:password', [
            'username' => 'pwcli',
            'password' => 'password123',
        ])->assertFailed();
    }

    public function testUserPasswordAcceptsStrongPassword()
    {
        $User = new User;
        $User->name     = 'PW CLI 2';
        $User->username = 'pwcli2';
        $User->email    = 'pwcli2@example.com';
        $User->password = bcrypt('Str0ng!Passw0rd');
        $User->save();

        $this->artisan('user:password', [
            'username' => 'pwcli2',
            'password' => 'An0ther!Str0ng',
        ])->assertSuccessful();
    }
}
