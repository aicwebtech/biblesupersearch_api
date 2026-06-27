<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\ApiAccessManager;

class SecurityHardeningTest extends TestCase
{
    protected array $server_cache = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->server_cache = $_SERVER;
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server_cache;
        parent::tearDown();
    }

    public function testTrustedDomainIsNullWithoutHeaders(): void
    {
        $this->assertNull(ApiAccessManager::trustedDomain());
    }

    public function testTrustedDomainUsesRefererWhenNoOrigin(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://referer.example.com/page';
        $this->assertSame('https://referer.example.com/page', ApiAccessManager::trustedDomain());
    }

    public function testTrustedDomainPrefersOriginOverReferer(): void
    {
        $_SERVER['HTTP_ORIGIN']  = 'https://origin.example.com';
        $_SERVER['HTTP_REFERER'] = 'https://referer.example.com/page';
        $this->assertSame('https://origin.example.com', ApiAccessManager::trustedDomain());
    }

    /**
     * The dynamic model-class generators must never execute generated PHP via eval().
     */
    public function testGeneratedModelClassesDoNotUseEval(): void
    {
        $files = [
            dirname(__DIR__, 2) . '/app/Models/Bible.php',
            dirname(__DIR__, 2) . '/app/Models/Books/BookAbstract.php',
        ];

        foreach($files as $file) {
            $this->assertStringNotContainsString('eval(', file_get_contents($file), "eval( found in {$file}");
        }
    }
}
