<?php

namespace Tests\Feature;

use App\InstallManager;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The installer is unauthenticated - first run setup has nobody to authenticate yet - so the
 * only thing standing between it and a populated database is the user count guard.
 *
 * app.installed is what normally keeps the installer away from a live database, and it is the
 * one piece of state most easily lost: a restored dump, a hand-cleared config value. When it is
 * lost, the installer must refuse rather than write: it must never take over an account that is
 * already there and give it access_level 100.
 *
 * The guard can afford to refuse on a single account because the installer no longer leaves one
 * behind - see Tests\Feature\InstallManagerAdministratorTest.
 */
class InstallManagerUserGuardTest extends TestCase
{
    /**
     * Throwaway accounts; removed by the caller in a finally.
     *
     * @param  int  $count
     * @return array<int, \App\User>
     */
    protected function makeUserFixtures(int $count): array
    {
        $Users = [];

        for($i = 0; $i < $count; $i++) {
            $suffix = bin2hex(random_bytes(8));

            $User = new User();
            $User->name     = 'Install Guard Fixture';
            $User->username = 'install_guard_' . $suffix;
            $User->email    = 'install_guard_' . $suffix . '@example.com';
            $User->password = bcrypt($suffix);
            $User->save();

            $Users[] = $User;
        }

        return $Users;
    }

    /**
     * @param  array<int, \App\User>  $Users
     * @return void
     */
    protected function removeUserFixtures(array $Users): void
    {
        foreach($Users as $User) {
            $User->forceDelete();
        }
    }

    /**
     * The case the guard exists for: exactly one account, which is what every real installation
     * of this application has, because the installer creates exactly one administrator. The
     * install has to be refused and that account left alone - not renamed, not given a new
     * password, and above all not handed access_level 100 on the strength of an anonymous POST.
     *
     * The count is forced rather than arranged in the database, which already holds whatever
     * accounts this test installation was set up with.
     */
    public function testASingleExistingAccountRefusesTheInstallAndIsNotElevated(): void
    {
        $Users = $this->makeUserFixtures(1);
        $User  = $Users[0];

        Artisan::shouldReceive('call')->never();
        Log::spy();

        config(['app.installed' => false]);

        try {
            $result = InstallManagerWithOneExistingUser::install(new Request());

            $this->assertSame(InstallManager::INSTALL_NOT_FRESH, $result, 'a populated database is not an installed one');
            $this->assertFalse(InstallManager::installLockExists(), 'the claim must come off again');

            Log::shouldHaveReceived('error')->once();

            $Fresh = User::find($User->id);

            $this->assertNotNull($Fresh);
            $this->assertSame($User->username, $Fresh->username, 'the installer must not rewrite an existing account');
            $this->assertSame($User->email, $Fresh->email);
            $this->assertSame($User->password, $Fresh->password, 'least of all the password');
            $this->assertNotSame(100, (int) $Fresh->access_level, 'the installer must never elevate an existing account');
        }
        finally {
            config(['app.installed' => true]);

            $this->removeUserFixtures($Users);
        }
    }

    /**
     * Artisan is mocked to refuse every call, so a guard that failed to hold would be caught
     * here rather than by key:generate rewriting the APP_KEY of whatever database these tests
     * are pointed at.
     */
    public function testAPopulatedUsersTableRefusesTheInstallBeforeAnythingIsWritten(): void
    {
        $Users = $this->makeUserFixtures(2);

        Artisan::shouldReceive('call')->never();
        Log::spy();

        // tests/TestCase::setUp() skips the whole suite unless the app is installed, so the
        // not-installed state can only be entered afterwards -- and has to be left again in a
        // finally, or later tests in this process inherit it.
        config(['app.installed' => false]);

        try {
            $result = InstallManagerWithoutTheInstalledFlag::install(new Request());

            $this->assertSame(InstallManager::INSTALL_NOT_FRESH, $result, 'a populated database is not an installed one');
            $this->assertFalse(InstallManager::installLockExists(), 'the claim must come off again');

            // The flag guard returns silently; the count guard is the one that has something
            // to tell the operator.
            Log::shouldHaveReceived('error')->once();

            foreach($Users as $User) {
                $Fresh = User::find($User->id);

                $this->assertNotNull($Fresh, 'a refused install must not remove an account');
                $this->assertSame($User->username, $Fresh->username, 'a refused install must not rewrite an account');
                $this->assertSame($User->email, $Fresh->email);
                $this->assertSame($User->password, $Fresh->password, 'least of all the password');
            }
        }
        finally {
            config(['app.installed' => true]);

            $this->removeUserFixtures($Users);
        }
    }

    /**
     * The count is what the guard turns on, and it has to answer for a database that is not
     * there yet: on a genuine first run the users table has not been migrated, and the
     * credentials may not even be valid. Nothing here may throw its way out of the installer.
     *
     * No setAccessible() call: reflection has ignored visibility since PHP 8.1 and the method is
     * deprecated in 8.5, which CI runs.
     */
    public function testTheUserCountIsReadWithoutThrowing(): void
    {
        $method = new \ReflectionMethod(InstallManager::class, 'existingUserCount');

        $before = $method->invoke(null);

        $this->assertIsInt($before);

        $Users = $this->makeUserFixtures(2);

        try {
            $this->assertSame($before + 2, $method->invoke(null), 'every account has to be counted');
        }
        finally {
            $this->removeUserFixtures($Users);
        }

        $this->assertSame($before, $method->invoke(null));
    }

    /**
     * A missing users table is the genuine first run, not a populated site.
     */
    public function testAMissingUsersTableCountsAsNoAccounts(): void
    {
        $method = new \ReflectionMethod(InstallManager::class, 'existingUserCount');

        \Schema::shouldReceive('hasTable')->with('users')->andReturn(false);

        $this->assertSame(0, $method->invoke(null));
    }

    /**
     * A database that cannot be read at all is also the genuine first run: on shared hosting
     * the operator is filling the credentials in on the very page that calls this.
     */
    public function testAnUnreadableDatabaseCountsAsNoAccounts(): void
    {
        $method = new \ReflectionMethod(InstallManager::class, 'existingUserCount');

        \Schema::shouldReceive('hasTable')->with('users')->andThrow(new \RuntimeException('Access denied for user'));

        $this->assertSame(0, $method->invoke(null));
    }
}

/**
 * The state the guard exists for: a database holding real accounts while the installed flag
 * says otherwise. Reached through late static binding rather than by clearing the flag, which
 * would leave this installation looking uninstalled if the process died mid-test.
 */
class InstallManagerWithoutTheInstalledFlag extends InstallManager
{
    static function isInstalledInDatabase(): bool
    {
        return FALSE;
    }
}

/**
 * One account, and no installed flag: a site whose single administrator is the one the
 * installer would otherwise have taken over.
 */
class InstallManagerWithOneExistingUser extends InstallManagerWithoutTheInstalledFlag
{
    static protected function existingUserCount(): int
    {
        return 1;
    }
}
