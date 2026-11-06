<?php

namespace Tests\Feature\Console\Commands;

use App\User;
use Tests\TestCase;

/*
 * The user:create and user:password CLI commands must enforce the same
 * password policy as the web paths (App\Providers\AppServiceProvider via
 * Password::defaults()): min(10) with mixed case, numbers, and symbols.
 *
 * The users are committed to the shared database, so each gets a unique pw_policy_ username
 * (TestCleanup::USER_PREFIXES, for a run killed before tearDown) and is removed in tearDown.
 */
class UserPasswordPolicyTest extends TestCase
{
    /**
     * A unique username, with any user of that name removed in tearDown
     */
    private function throwawayUsername(): string
    {
        $username = 'pw_policy_' . bin2hex(random_bytes(4));
        $this->deferCleanup(fn() => User::where('username', $username)->delete());

        return $username;
    }

    public function testUserCreateRejectsWeakPassword(): void
    {
        $username = $this->throwawayUsername();

        $this->artisan('user:create', [
            'email_address' => $username . '@example.com',
            'username'      => $username,
            'password'      => 'password123',
        ])->assertFailed();

        $this->assertNull(User::where('username', $username)->first());
    }

    public function testUserCreateAcceptsStrongPassword(): void
    {
        $username = $this->throwawayUsername();

        $this->artisan('user:create', [
            'email_address' => $username . '@example.com',
            'username'      => $username,
            'password'      => 'Str0ng!Passw0rd',
        ])->assertSuccessful();

        $this->assertNotNull(User::where('username', $username)->first());
    }

    public function testUserPasswordRejectsWeakPassword(): void
    {
        $username = $this->throwawayUsername();
        $this->makeUser($username);

        $this->artisan('user:password', [
            'username' => $username,
            'password' => 'password123',
        ])->assertFailed();
    }

    public function testUserPasswordAcceptsStrongPassword(): void
    {
        $username = $this->throwawayUsername();
        $this->makeUser($username);

        $this->artisan('user:password', [
            'username' => $username,
            'password' => 'An0ther!Str0ng',
        ])->assertSuccessful();
    }

    private function makeUser(string $username): void
    {
        $User = new User;
        $User->name     = 'PW CLI';
        $User->username = $username;
        $User->email    = $username . '@example.com';
        $User->password = bcrypt('Str0ng!Passw0rd');
        $User->save();
    }
}
