<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
 * Verifies the single source of truth for password strength defined in
 * App\Providers\AppServiceProvider via Password::defaults(). All password
 * validation (registration, reset, install) resolves to this policy:
 * min(10) with mixed case, numbers, and symbols.
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
        $this->assertFalse($this->passes('Ab1!efgh'));
    }

    public function testMissingNumberIsRejected()
    {
        $this->assertFalse($this->passes('NoNumbers!!!'));
    }

    public function testMissingSymbolIsRejected()
    {
        $this->assertFalse($this->passes('NoSymbols123'));
    }

    public function testMissingUppercaseIsRejected()
    {
        $this->assertFalse($this->passes('lowercase123!'));
    }

    public function testMissingLowercaseIsRejected()
    {
        $this->assertFalse($this->passes('UPPERCASE123!'));
    }

    public function testWeakCommonPasswordIsRejected()
    {
        // Previously accepted under the old min(8)->letters()->numbers() policy.
        $this->assertFalse($this->passes('password123'));
    }

    public function testStrongPasswordIsAccepted()
    {
        $this->assertTrue($this->passes('Str0ng!Passw0rd'));
    }
}
