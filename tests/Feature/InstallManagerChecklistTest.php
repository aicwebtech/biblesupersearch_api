<?php

namespace Tests\Feature;

use App\InstallManager;
use Tests\TestCase;

/**
 * The installer's requirement checklist is what the install page renders and checks the server
 * against. It reads composer.json for the PHP version, so it needs base_path() and is a
 * feature test.
 *
 * install(), uninstall() and checkSettings() rewrite the database and the environment file and
 * are not executed here.
 */
class InstallManagerChecklistTest extends TestCase
{
    public function testTheApplicationReportsItselfInstalled(): void
    {
        $this->assertTrue(InstallManager::isInstalled(), 'the test suite requires an installed application');
    }

    public function testIsInstalledFollowsTheConfigFlag(): void
    {
        config(['app.installed' => false]);

        $this->assertFalse(InstallManager::isInstalled());

        config(['app.installed' => true]);

        $this->assertTrue(InstallManager::isInstalled());
    }

    /**
     * The required PHP version is taken from composer.json rather than duplicated, so the
     * install page cannot advertise a different floor from the one Composer enforces.
     */
    public function testTheRequiredPhpVersionComesFromComposerJson(): void
    {
        $checklist = InstallManager::getChecklist();

        $composer = json_decode(file_get_contents(base_path('composer.json')));

        $this->assertSame(substr($composer->require->php, 2), $checklist['php_version']);
    }

    public function testTheRequiredPhpVersionIsAUsableVersionString(): void
    {
        $checklist = InstallManager::getChecklist();

        $this->assertMatchesRegularExpression('/^\d+\.\d+(\.\d+)?$/', $checklist['php_version']);
        $this->assertTrue(version_compare(PHP_VERSION, $checklist['php_version'], '>='));
    }

    /**
     * Reading composer.json is guarded by a one-shot flag, so repeated calls must keep
     * returning the resolved version rather than re-reading or blanking it.
     */
    public function testTheChecklistIsStableAcrossCalls(): void
    {
        $this->assertSame(InstallManager::getChecklist(), InstallManager::getChecklist());
    }

    /**
     * Every extension the installer demands should actually be loaded here - the suite is
     * running, so a listed-but-absent extension would mean the checklist is wrong rather than
     * the server being unfit.
     */
    public function testEveryRequiredExtensionIsPresentOnThisServer(): void
    {
        $checklist = InstallManager::getChecklist();

        foreach ($checklist['php_extensions_required'] as $extension) {
            $this->assertTrue(
                extension_loaded(strtolower($extension)),
                "{$extension} is listed as required but is not loaded"
            );
        }
    }

    public function testMysqlIsAnOfferedDatabase(): void
    {
        $checklist = InstallManager::getChecklist();

        $this->assertArrayHasKey('mysql', $checklist['database']);
    }

    /**
     * The writable list is what the installer checks before it will proceed; every entry must
     * be a real path relative to the project root, or the check silently passes on a
     * directory that does not exist.
     */
    public function testEveryWritablePathExists(): void
    {
        $checklist = InstallManager::getChecklist();

        foreach ($checklist['writable'] as $path) {
            $this->assertFileExists(base_path($path), "{$path} is on the writable checklist");
        }
    }

    /**
     * Deliberately not asserted: whether those paths are currently writable. That is a fact
     * about the machine the suite happens to run on - storage/logs/laravel.log is typically
     * owned by the web server user - and asserting it would make the suite fail for
     * permissions reasons unrelated to the code. Checking it is the installer's job, at
     * install time, on the target server.
     */
    public function testTheWritableChecklistCoversStorageAndBibleDirectories(): void
    {
        $checklist = InstallManager::getChecklist();

        $this->assertContains('storage/framework', $checklist['writable']);
        $this->assertContains('bootstrap/cache', $checklist['writable']);
        $this->assertContains('bibles/modules', $checklist['writable'], 'imports write here');
        $this->assertContains('bibles/rendered', $checklist['writable'], 'downloads are rendered here');
    }
}
