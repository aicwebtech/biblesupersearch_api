<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AppInstall;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/*
 * Covers the admin account prompt requirements (BSS-275) that the CLI
 * installer collects: name, username, email, password + confirmation.
 */
class AppInstallTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function passes(array $data): bool
    {
        $rules = (new AppInstall())->adminUserValidationRules();

        return Validator::make($data, $rules)->passes();
    }

    public function testValidAdminDetailsPass(): void
    {
        $this->assertTrue($this->passes([
            'name'                  => 'Site Admin',
            'username'              => 'admin_user',
            'email'                 => 'admin@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]));
    }

    public function testNameIsRequired(): void
    {
        $this->assertFalse($this->passes([
            'name'                  => '',
            'username'              => 'admin_user',
            'email'                 => 'admin@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]));
    }

    public function testUsernameMustBeAtLeastEightAlphaDashChars(): void
    {
        $this->assertFalse($this->passes([
            'name'                  => 'Site Admin',
            'username'              => 'admin',
            'email'                 => 'admin@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]));

        $this->assertFalse($this->passes([
            'name'                  => 'Site Admin',
            'username'              => 'has spaces',
            'email'                 => 'admin@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]));
    }

    public function testEmailMustBeValid(): void
    {
        $this->assertFalse($this->passes([
            'name'                  => 'Site Admin',
            'username'              => 'admin_user',
            'email'                 => 'not-an-email',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]));
    }

    public function testWeakPasswordIsRejected(): void
    {
        $this->assertFalse($this->passes([
            'name'                  => 'Site Admin',
            'username'              => 'admin_user',
            'email'                 => 'admin@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]));
    }

    public function testMismatchedPasswordConfirmationIsRejected(): void
    {
        $this->assertFalse($this->passes([
            'name'                  => 'Site Admin',
            'username'              => 'admin_user',
            'email'                 => 'admin@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'different123',
        ]));
    }
}
