<?php

namespace Tests\Feature\Providers;

use App\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * AuthServiceProvider defines the admin-access gate that guards the admin area.
 *
 * The users here are unsaved in-memory instances - the gate only reads access_level, so no
 * row is created and no content data is touched.
 */
class AuthServiceProviderTest extends TestCase
{
    private function userWithAccessLevel(?int $level): User
    {
        $user = new User();
        $user->access_level = $level;

        return $user;
    }

    public function testAdminAccessIsGrantedAtLevelOneHundred(): void
    {
        $this->assertTrue(Gate::forUser($this->userWithAccessLevel(100))->allows('admin-access'));
    }

    public function testAdminAccessIsGrantedAboveLevelOneHundred(): void
    {
        $this->assertTrue(Gate::forUser($this->userWithAccessLevel(255))->allows('admin-access'));
    }

    /**
     * One below the threshold must be refused - this is the boundary the admin middleware
     * leans on, so an off-by-one here would expose the whole admin area.
     */
    public function testAdminAccessIsDeniedJustBelowTheThreshold(): void
    {
        $this->assertFalse(Gate::forUser($this->userWithAccessLevel(99))->allows('admin-access'));
    }

    public function testAdminAccessIsDeniedForAnOrdinaryUser(): void
    {
        $this->assertFalse(Gate::forUser($this->userWithAccessLevel(0))->allows('admin-access'));
    }

    public function testAdminAccessIsDeniedWhenAccessLevelIsNotSet(): void
    {
        $this->assertFalse(Gate::forUser($this->userWithAccessLevel(null))->allows('admin-access'));
    }
}
