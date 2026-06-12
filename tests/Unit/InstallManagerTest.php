<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\InstallManager;

class InstallManagerTest extends TestCase
{
    public function testModRewriteEnabledReturnsBool(): void
    {
        $result = InstallManager::modRewriteEnabled();
        $this->assertIsBool($result);
    }

    public function testModRewriteEnabledReturnsTrueOnNonApache(): void
    {
        // On non-Apache servers (or CLI), apache_get_modules() does not exist,
        // so mod_rewrite is assumed enabled.
        if (function_exists('apache_get_modules')) {
            $this->markTestSkipped('Skipped: apache_get_modules() exists on this server');
        }

        $this->assertTrue(InstallManager::modRewriteEnabled());
    }
}
