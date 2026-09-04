<?php

namespace Tests\Feature\Controllers;

use App\Http\Controllers\Admin\InstallController;
use App\InstallManager;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The installer is unauthenticated by design - first run setup has nobody to authenticate yet -
 * so what it renders and how it refuses are both security surfaces.
 *
 * install() itself is never executed here: it runs key:generate and migrate --force against
 * whatever database is configured. Every case below is arranged so the method returns at one of
 * its guards, before it writes anything.
 */
class InstallControllerTest extends TestCase
{
    /**
     * tests/TestCase::setUp() skips the whole suite unless the app is installed, so the
     * not-installed state can only be entered after setUp() has run. Restored in a finally by
     * every caller - a leaked FALSE would skip or break later tests in the same process.
     */
    private function asUninstalled(callable $callback): void
    {
        config(['app.installed' => false]);

        try {
            $callback();
        }
        finally {
            config(['app.installed' => true]);
        }
    }

    public function testTheCheckPageDoesNotDiscloseTheDatabasePassword(): void
    {
        $password = config('database.connections.' . config('database.default') . '.password');

        $this->asUninstalled(function() use ($password) {
            $response = $this->post(route('admin.install.check'));

            $response->assertStatus(200);
            $response->assertSee('DB_PASSWORD', false);

            if(!empty($password)) {
                $response->assertDontSee($password, false);
            }
        });
    }

    /**
     * The checklist labels are built from configuration the operator controls and were rendered
     * through a raw echo, so a value carrying markup reached the page as markup.
     */
    public function testChecklistLabelsAreEscaped(): void
    {
        $connection = config('database.default');

        $this->asUninstalled(function() use ($connection) {
            $original = config('database.connections.' . $connection . '.host');

            // Display only - checkSettings() probes the connection through the already resolved
            // DB facade, not through this value.
            config(['database.connections.' . $connection . '.host' => '<script>alert(1)</script>']);

            try {
                $response = $this->post(route('admin.install.check'));

                $response->assertStatus(200);
                $response->assertDontSee('<script>alert(1)</script>', false);
                $response->assertSee('&lt;script&gt;', false);
            }
            finally {
                config(['database.connections.' . $connection . '.host' => $original]);
            }
        });
    }

    /**
     * A failed install used to fall out of an empty else branch and return a blank HTTP 200.
     * Leaving app.installed TRUE makes install() return at its first guard, so nothing is written.
     */
    public function testAnAlreadyInstalledApplicationIsToldSo(): void
    {
        $response = (new InstallController())->install(new Request());

        $this->assertSame(409, $response->status());
        $this->assertStringContainsString('already installed', $response->getContent());
    }

    /**
     * The claim is what makes the installer one-time. With it held, a second request has to be
     * refused before key:generate or migrate can run.
     */
    public function testASecondConcurrentInstallIsRefused(): void
    {
        $this->assertTrue(
            InstallManager::claimInstallLock(),
            'the claim must be held for this test, or install() would run for real'
        );

        try {
            $this->asUninstalled(function() {
                $response = (new InstallController())->install(new Request());

                $this->assertSame(409, $response->status());
                $this->assertStringContainsString('already running', $response->getContent());
            });
        }
        finally {
            InstallManager::releaseInstallLock();
        }
    }

    /**
     * isInstalled() can be answered from a config cache built before the install finished, which
     * is how a request reaches install() on a site that is in fact installed. The database has to
     * get the last word before anything is written: key:generate would rotate APP_KEY on a live
     * site, and migrate --force would re-run against a populated database.
     *
     * Unlike the other cases here this one does enter install(), so the assertions below are what
     * establish that it returned before writing anything.
     */
    public function testAnInstalledDatabaseRefusesTheInstallBeforeAnythingIsWritten(): void
    {
        $key   = config('app.key');
        $users = \App\User::count();

        $this->asUninstalled(function() use ($key, $users) {
            $result = InstallManager::install(new Request());

            $this->assertSame(InstallManager::INSTALL_ALREADY_INSTALLED, $result);
            $this->assertSame($key, config('app.key'), 'the application key must survive a refused install');
            $this->assertSame($users, \App\User::count(), 'a refused install must not touch the users table');
            $this->assertFalse(InstallManager::installLockExists(), 'the claim must come off again');
        });
    }

    /**
     * The installer endpoints carry no authentication, so a throttle is the only thing bounding
     * repeated attempts at a multi-minute migration.
     */
    public function testTheInstallerRoutesAreThrottled(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.install.config.process');

        $this->assertNotNull($route);
        $this->assertContains('throttle:20,1', $route->gatherMiddleware());
    }
}
