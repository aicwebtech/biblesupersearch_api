<?php

namespace Tests\Feature;

use App\InstallManager;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * What keeps a half-finished install recoverable.
 *
 * The user count guard refuses to run against a database that already holds accounts, which is
 * only safe because the installer can no longer leave one behind: the administrator row and the
 * app.installed flag are written together, last, in one transaction. Were they ever to come
 * apart, the site would be unrecoverable from the browser - the guard would refuse every retry
 * while InstallRedirect pinned every other URL to /install.
 *
 * install() is never run to completion here: it would run key:generate and migrate --force
 * against whatever database these tests are pointed at.
 */
class InstallManagerAdministratorTest extends TestCase
{
    /**
     * @param  string $username
     * @return \Illuminate\Http\Request
     */
    protected function makeInstallRequest(string $username): Request
    {
        return new Request([
            'name'     => 'Administrator Fixture',
            'username' => $username,
            'email'    => $username . '@example.com',
            'password' => 'fixture-' . bin2hex(random_bytes(8)),
        ]);
    }

    /**
     * @param  string $username
     * @return void
     */
    protected function removeUserFixture(string $username): void
    {
        $User = User::where('username', $username)->first();

        if($User) {
            $User->forceDelete();
        }
    }

    /**
     * The invariant the user count guard rests on: if the flag cannot be written, the
     * administrator must not exist either. Without the transaction this leaves behind the
     * orphan row that no later attempt can get past.
     */
    public function testAFailedInstalledFlagTakesTheAdministratorWithIt(): void
    {
        $username = 'install_admin_' . bin2hex(random_bytes(8));

        try {
            try {
                InstallManagerThatCannotWriteTheFlag::createAdministrator($this->makeInstallRequest($username));

                $this->fail('the write failure has to reach the caller, or install() would report success');
            }
            catch (\RuntimeException $e) {
                $this->assertSame('config write failed', $e->getMessage());
            }

            $this->assertSame(
                0,
                User::where('username', $username)->count(),
                'the administrator has to be rolled back with the flag, or the install can never be retried'
            );
        }
        finally {
            $this->removeUserFixture($username);
        }
    }

    /**
     * The other half of the same transaction: when the flag can be written, the administrator
     * is the account the operator typed, and it is given access_level 100.
     *
     * The flag write is stubbed out rather than performed - app.installed is installed content
     * on this database, not something a test may set.
     */
    public function testTheAdministratorIsCreatedWithAdministratorAccess(): void
    {
        $username = 'install_admin_' . bin2hex(random_bytes(8));
        $request  = $this->makeInstallRequest($username);

        try {
            InstallManagerThatSkipsTheFlag::createAdministrator($request);

            $User = User::where('username', $username)->first();

            $this->assertNotNull($User, 'the administrator has to be committed with the flag');
            $this->assertSame($request->input('name'), $User->name);
            $this->assertSame($request->input('email'), $User->email);

            // Mass assignment would silently drop this (see App\User), leaving an administrator
            // with no more access than an ordinary account.
            $this->assertSame(100, (int) $User->access_level);

            $this->assertTrue(\Hash::check($request->input('password'), $User->password), 'the stored password has to be the hash of what was typed');
        }
        finally {
            $this->removeUserFixture($username);
        }
    }

    /**
     * A migration that fails has to be reported. Its exit code used to be assigned and dropped,
     * so the install carried on into the Bible table and the administrator insert and came apart
     * there instead - a raw framework 500, and an administrator row if the migration had got far
     * enough to create the table.
     */
    public function testAFailedMigrationStopsTheInstall(): void
    {
        $users = User::count();

        Artisan::shouldReceive('call')->once()->with('key:generate')->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => TRUE])->andReturn(1);
        Log::spy();

        // tests/TestCase::setUp() skips the whole suite unless the app is installed, so the
        // not-installed state can only be entered afterwards, and has to be left again in a
        // finally or later tests in this process inherit it.
        config(['app.installed' => false]);

        try {
            $result = InstallManagerOnAFreshDatabase::install($this->makeInstallRequest('install_admin_unused'));

            $this->assertSame(InstallManager::INSTALL_FAILED, $result);
            $this->assertFalse(InstallManager::installLockExists(), 'the claim must come off again');
            $this->assertSame($users, User::count(), 'nothing may be written after a failed migration');

            Log::shouldHaveReceived('error')->once();
        }
        finally {
            config(['app.installed' => true]);
        }
    }
}

/**
 * A fresh database as far as the installer's two guards can tell, so install() gets as far as
 * the Artisan calls mocked above. Reached through late static binding rather than by touching
 * the database these tests run against.
 */
class InstallManagerOnAFreshDatabase extends InstallManager
{
    static function isInstalledInDatabase(): bool
    {
        return FALSE;
    }

    static protected function existingUserCount(): int
    {
        return 0;
    }
}

/**
 * The flag write fails, which is what the transaction exists for.
 *
 * The wrapper is what a test can call: late static binding only picks the override up when the
 * call is made through this subclass.
 */
class InstallManagerThatCannotWriteTheFlag extends InstallManager
{
    static public function createAdministrator(Request $request): void
    {
        static::createAdministratorAndMarkInstalled($request);
    }

    static protected function markInstalled(): void
    {
        throw new \RuntimeException('config write failed');
    }
}

/**
 * The flag write succeeds without being performed: app.installed is already TRUE on this
 * database, and setting configs is not this test's business.
 */
class InstallManagerThatSkipsTheFlag extends InstallManager
{
    static public function createAdministrator(Request $request): void
    {
        static::createAdministratorAndMarkInstalled($request);
    }

    static protected function markInstalled(): void
    {
    }
}
