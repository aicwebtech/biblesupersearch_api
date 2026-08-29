<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\InstallManager;

/**
 * The two InstallManager helpers that resolve without an application: the importable-directory
 * list that drives premium class substitution, and the server URL derived from $_SERVER.
 *
 * install(), uninstall() and checkSettings() rewrite the database and the environment file and
 * are deliberately not executed anywhere in the suite.
 *
 * Named InstallManagerPathsTest so it does not collide with the existing
 * Tests\Unit\InstallManagerTest.
 */
class InstallManagerPathsTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    // -----------------------------------------------------------------------
    // Importable directories
    // -----------------------------------------------------------------------

    /**
     * Helpers::transformClassName() reads index 2 of this list to build the premium class
     * name, so the list's order is load-bearing rather than incidental.
     */
    public function testTheImportableDirectoryUsedForClassSubstitutionIsStable(): void
    {
        $this->assertSame('Models\Misc', InstallManager::getImportableDir()[2]);
    }

    public function testImportableDirectoriesCoverTheGeneratedNamespaces(): void
    {
        $dirs = InstallManager::getImportableDir();

        $this->assertContains('Models\Books', $dirs);
        $this->assertContains('Models\Verses', $dirs);
        $this->assertContains('Traits', $dirs);
    }

    /**
     * The entries that are checked into the repository must name real namespaces under app/.
     *
     * Two entries are excluded deliberately:
     *  - 'Models\Misc' is generated at install time and gitignored, so it is legitimately
     *    absent from a clean checkout.
     *  - 'Renders\Extras' names no directory at all - the namespace is App\Renderers\Extras,
     *    and there is no app/Renders. Nothing consumes that entry today (Helpers reads only
     *    index 2), so it is inert rather than broken. Reported rather than fixed; this ticket
     *    does not change production code.
     */
    public function testTrackedImportableDirectoriesNameRealNamespaces(): void
    {
        $appPath  = __DIR__ . '/../../app/';
        $generated = ['Models\Misc', 'Renders\Extras'];

        $checked = 0;

        foreach (InstallManager::getImportableDir() as $dir) {
            if (in_array($dir, $generated, true)) {
                continue;
            }

            $path = $appPath . str_replace('\\', '/', $dir);

            $this->assertDirectoryExists($path, "{$dir} should be a real directory under app/");
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }

    public function testTheRendersExtrasEntryDoesNotResolve(): void
    {
        $this->assertDirectoryDoesNotExist(
            __DIR__ . '/../../app/Renders',
            'if app/Renders now exists, the getImportableDir typo note above is stale'
        );
    }

    // -----------------------------------------------------------------------
    // Server URL
    // -----------------------------------------------------------------------

    public function testServerUrlPrefersTheHttpHost(): void
    {
        $_SERVER['HTTP_HOST']   = 'bible.example.com';
        $_SERVER['SERVER_NAME'] = 'ignored.example.com';
        unset($_SERVER['HTTPS']);

        $this->assertSame('http://bible.example.com', InstallManager::getServerUrl());
    }

    public function testServerUrlFallsBackToTheServerName(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS']);
        $_SERVER['SERVER_NAME'] = 'fallback.example.com';

        $this->assertSame('http://fallback.example.com', InstallManager::getServerUrl());
    }

    /**
     * With neither present - a CLI install, for instance - a placeholder is used rather than
     * building a URL with an empty host.
     */
    public function testServerUrlUsesAPlaceholderWhenNoHostIsKnown(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME'], $_SERVER['HTTPS']);

        $this->assertSame('http://example.com', InstallManager::getServerUrl());
    }

    public function testServerUrlUsesHttpsWhenTheRequestWasSecure(): void
    {
        $_SERVER['HTTP_HOST'] = 'bible.example.com';
        $_SERVER['HTTPS']     = 'on';

        $this->assertSame('https://bible.example.com', InstallManager::getServerUrl());
    }

    /**
     * Apache sets HTTPS to the empty string on plain HTTP, which must not be read as secure.
     */
    public function testAnEmptyHttpsValueIsNotTreatedAsSecure(): void
    {
        $_SERVER['HTTP_HOST'] = 'bible.example.com';
        $_SERVER['HTTPS']     = '';

        $this->assertSame('http://bible.example.com', InstallManager::getServerUrl());
    }
}
