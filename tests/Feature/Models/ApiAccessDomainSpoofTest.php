<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\ApiAccessManager;

/*
 * Regression tests for the API access / rate-limit bypass: the requesting
 * domain must be derived from browser-set headers, never from a client-supplied
 * `domain` request parameter. See App\ApiAccessManager::trustedDomain().
 */
class ApiAccessDomainSpoofTest extends TestCase
{
    protected array $server_cache = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->server_cache = $_SERVER;

        config([
            'bss.public_access'          => 1,
            'bss.daily_access_limit'     => 500,
            'bss.daily_access_whitelist' => 'trusted-partner.com',
        ]);

        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'api.example.com';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
    }

    public function tearDown(): void
    {
        $_SERVER = $this->server_cache;
        parent::tearDown();
    }

    /** Spoofing the server's own host via the `domain` param must not grant unlimited access. */
    public function testSpoofedSameDomainParamIsIgnored()
    {
        $request = Request::create('/api/query', 'GET', ['domain' => 'api.example.com']);
        $Access  = ApiAccessManager::lookUp($request);

        $this->assertNotSame(0, $Access->getAccessLimit(), 'Spoofed same-domain param must not yield unlimited access');
        $this->assertNull($Access->domain, 'Record must be keyed by IP, not the spoofed domain param');

        $Access->delete();
    }

    /** Spoofing a whitelisted domain via the `domain` param must not grant unlimited access. */
    public function testSpoofedWhitelistDomainParamIsIgnored()
    {
        $request = Request::create('/api/query', 'GET', ['domain' => 'trusted-partner.com']);
        $Access  = ApiAccessManager::lookUp($request);

        $this->assertNotSame(0, $Access->getAccessLimit(), 'Spoofed whitelisted-domain param must not yield unlimited access');
        $this->assertNull($Access->domain, 'Record must be keyed by IP, not the spoofed domain param');

        $Access->delete();
    }

    /** The per-request domain is taken from the browser Origin header, not the param. */
    public function testOriginHeaderDeterminesDomainOverParam()
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://embedder.example.org';

        $Access = ApiAccessManager::lookUpByInput(['domain' => 'api.example.com']);

        $this->assertSame('embedder.example.org', $Access->domain, 'Origin header must take precedence over the domain param');

        $Access->delete();
    }
}
