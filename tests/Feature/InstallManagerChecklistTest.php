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
     * Finds the checklist row whose label begins with the given text.
     *
     * @return array<string, mixed>|null
     */
    private function findChecklistRow(array $checklist, string $label_prefix): ?array
    {
        foreach($checklist as $row) {
            if(array_key_exists('label', $row) && strpos($row['label'], $label_prefix) === 0) {
                return $row;
            }
        }

        return NULL;
    }

    /**
     * A credential row is green only when that value is configured and the connection it feeds
     * came up. Both halves are asserted here because the two tests used to sit inside a single
     * empty() over the pair, where the connection half was easy to read straight past.
     */
    public function testACredentialRowIsGreenOnlyWhenItsValueIsConfigured(): void
    {
        if(static::databaseIsFileBased()) {
            $this->markTestSkipped('a file database reports no credential rows - see checkSettings()');
        }

        $connection = config('database.default');
        $db_info    = config('database.connections.' . $connection);

        list($checklist, $success) = InstallManager::checkSettings();

        $connected = $this->findChecklistRow($checklist, 'Able to Connect');

        $this->assertNotNull($connected);
        $this->assertTrue($connected['success'], 'the suite is running against this database, so it must be reachable');

        // Whichever credentials this environment actually sets must read as good while connected.
        foreach(['host' => 'DB_HOST', 'database' => 'DB_DATABASE', 'username' => 'DB_USERNAME'] as $key => $label_prefix) {
            if(empty($db_info[$key])) {
                continue;
            }

            $row = $this->findChecklistRow($checklist, $label_prefix);

            $this->assertNotNull($row, $label_prefix . ' must be reported');
            $this->assertTrue($row['success'], $label_prefix . ' is set and the database is reachable');
        }
    }

    /**
     * The other half: an unset value is not excused by a working connection. Display only - a
     * connection carries the credentials it was built with, not these values.
     */
    public function testACredentialRowIsNotGreenWhenItsValueIsMissing(): void
    {
        if(static::databaseIsFileBased()) {
            $this->markTestSkipped('a file database reports no credential rows - see checkSettings()');
        }

        $connection = config('database.default');
        $original   = config('database.connections.' . $connection . '.username');

        config(['database.connections.' . $connection . '.username' => '']);

        try {
            list($checklist, $success) = InstallManager::checkSettings();

            $row = $this->findChecklistRow($checklist, 'DB_USERNAME');

            $this->assertNotNull($row);
            $this->assertFalse($row['success'], 'an unset username must not be reported as good');
            $this->assertFalse($success, 'and it must fail the checklist as a whole');
        }
        finally {
            config(['database.connections.' . $connection . '.username' => $original]);
        }
    }

    /**
     * And the connection half: fully configured credentials are still not good news when nothing
     * answers at the other end. This is the half that was easiest to lose, since every value
     * being set makes the rows look green if the connection is forgotten.
     */
    public function testCredentialRowsAreNotGreenWhenTheDatabaseIsUnreachable(): void
    {
        if(static::databaseIsFileBased()) {
            $this->markTestSkipped('a file database reports no credential rows - see checkSettings()');
        }

        $connection = config('database.default');
        $unreachable = 'bss_unreachable_' . getmypid();

        // A copy of the real connection pointed at a port nothing listens on, so the credentials
        // stay set while the connection cannot come up. Port 1 is refused outright rather than
        // left to time out.
        config([
            'database.connections.' . $unreachable => array_merge(config('database.connections.' . $connection), [
                'host' => '127.0.0.1',
                'port' => 1,
            ]),
            'database.default' => $unreachable,
        ]);

        try {
            list($checklist, $success) = InstallManager::checkSettings();

            $connected = $this->findChecklistRow($checklist, 'Able to Connect');

            $this->assertNotNull($connected);
            $this->assertFalse($connected['success'], 'nothing is listening, so the probe must fail');

            foreach(['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $label_prefix) {
                $row = $this->findChecklistRow($checklist, $label_prefix);

                $this->assertNotNull($row, $label_prefix . ' must be reported');
                $this->assertFalse($row['success'], $label_prefix . ' is set, but it is not good news while the database is unreachable');
            }

            $this->assertFalse($success);
        }
        finally {
            config(['database.default' => $connection]);

            \DB::purge($unreachable);
        }
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
