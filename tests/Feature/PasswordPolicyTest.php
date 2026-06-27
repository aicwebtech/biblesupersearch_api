<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
 * Verifies the single source of truth for password strength defined in
 * App\Providers\AppServiceProvider via Password::defaults(). All password
 * validation (registration, reset, install) resolves to this policy.
 */
class PasswordPolicyTest extends TestCase
{
    private function passes(string $password): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::defaults()]]
        )->passes();
    }

    public function testTooShortIsRejected()
    {
        $this->assertFalse($this->passes('Pass12'));
    }

    public function testMissingNumberIsRejected()
    {
        $this->assertFalse($this->passes('onlyletters'));
    }

    public function testMissingLetterIsRejected()
    {
        $this->assertFalse($this->passes('12345678'));
    }

    public function testStrongPasswordIsAccepted()
    {
        $this->assertTrue($this->passes('password123'));
    }
}
