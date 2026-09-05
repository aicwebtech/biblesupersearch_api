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
     * checkSettings() runs before there is anyone to authenticate, so it must not hand the
     * database password back to whoever asked. It used to build 'DB_PASSWORD (' . $password . ')'
     * as a checklist label.
     */
    public function testTheChecklistNeverCarriesTheDatabasePassword(): void
    {
        $connection = config('database.default');
        $password   = config('database.connections.' . $connection . '.password');

        list($checklist, $success) = InstallManager::checkSettings();

        $labels = array_column($checklist, 'label');

        $this->assertNotEmpty($labels);

        if(!empty($password)) {
            foreach($labels as $label) {
                $this->assertStringNotContainsString($password, $label, 'the database password must not reach the checklist');
            }
        }

        // A file database is reached by path rather than by credentials, so checkSettings()
        // emits the file and directory rows in place of the DB_* ones. There is no password
        // row to check the wording of - only the absence above still has to hold.
        if(static::databaseIsFileBased()) {
            $file_rows = array_values(array_filter($labels, function($label) {
                return strpos($label, 'DB file is writable') === 0;
            }));

            $this->assertNotEmpty($file_rows, 'the operator still needs to know whether the database file is usable');

            return;
        }

        $password_rows = array_values(array_filter($labels, function($label) {
            return strpos($label, 'DB_PASSWORD') === 0;
        }));

        $this->assertNotEmpty($password_rows, 'the operator still needs to know whether a password is configured');
        $this->assertMatchesRegularExpression('/^DB_PASSWORD \((set|not set)\)$/', $password_rows[0]);
    }

    /**
     * Every label is rendered escaped now, so no label may rely on carrying its own markup - the
     * one that did, the database connection error, was split into a label and a detail.
     */
    public function testNoChecklistLabelCarriesMarkup(): void
    {
        list($checklist, $success) = InstallManager::checkSettings();

        foreach($checklist as $row) {
            if(!array_key_exists('label', $row)) {
                continue;
            }

            $this->assertStringNotContainsString('<', $row['label'], 'labels are escaped, so markup in one would be shown literally');
        }
    }

    /**
     * The installed flag has to survive a stale config cache: LoadSoftConfiguration skips the
     * database entirely when bootstrap/cache/config.php exists, which would otherwise freeze
     * app.installed at its FALSE default and leave the installer reachable on a live site.
     */
    public function testTheInstalledFlagCanBeReadStraightFromTheDatabase(): void
    {
        $this->assertTrue(InstallManager::isInstalledInDatabase());
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
     * The required-extension list is the installer's contract with the operator, so it is
     * asserted as data: named, unique, and covering the extensions the application genuinely
     * cannot run without.
     *
     * Deliberately not asserted: whether those extensions are loaded on this machine. That is
     * a property of the PHP build the suite happens to run under - bcmath, for instance, is
     * not installed for every version in the CI matrix - and checking it here would turn a
     * missing local package into a test failure. Verifying the server is the installer's job,
     * at install time, on the target server.
     */
    public function testTheRequiredExtensionListIsWellFormed(): void
    {
        $required = InstallManager::getChecklist()['php_extensions_required'];

        $this->assertNotEmpty($required);
        $this->assertSame(array_unique($required), $required, 'no extension should be listed twice');

        foreach ($required as $extension) {
            $this->assertIsString($extension);
            $this->assertNotEmpty($extension);
        }
    }

    /**
     * The extensions the application would fail outright without: PDO for every query,
     * Mbstring for the multi-byte text handling throughout, and Zip for module import/export.
     */
    public function testTheRequiredListCoversTheLoadBearingExtensions(): void
    {
        $required = array_map('strtolower', InstallManager::getChecklist()['php_extensions_required']);

        $this->assertContains('pdo', $required);
        $this->assertContains('mbstring', $required);
        $this->assertContains('zip', $required);
        $this->assertContains('json', $required);
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
            $full = base_path($path);

            // A file entry - storage/logs/laravel.log - is not in git and is created lazily on
            // the first log write, so on a fresh checkout only its directory is guaranteed.
            if(pathinfo($path, PATHINFO_EXTENSION)) {
                $this->assertDirectoryExists(dirname($full), "the directory holding {$path} is on the writable checklist");
                continue;
            }

            $this->assertDirectoryExists($full, "{$path} is on the writable checklist");
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
