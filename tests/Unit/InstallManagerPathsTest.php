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
     * Only 'Models\Misc' is excluded: it is generated at install time and gitignored, so it is
     * legitimately absent from a clean checkout.
     */
    public function testTrackedImportableDirectoriesNameRealNamespaces(): void
    {
        $appPath  = __DIR__ . '/../../app/';
        $generated = ['Models\Misc'];

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

    /**
     * The entry names the renderers' extras namespace, App\Renderers\Extras. It read
     * 'Renders\Extras' until BSS-285 - a namespace that has never existed.
     */
    public function testTheRenderersExtrasEntryResolves(): void
    {
        $this->assertContains('Renderers\Extras', InstallManager::getImportableDir());
        $this->assertDirectoryExists(__DIR__ . '/../../app/Renderers/Extras');
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
