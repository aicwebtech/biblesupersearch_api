<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * HttpsRedirect is global, so a deployment that terminates TLS upstream needs
 * the forwarded scheme to be believed. Without a trusted proxy
 * Request::secure() is permanently FALSE and every request redirects to a URL
 * that redirects again -- an infinite loop rather than a working site.
 *
 * The other half of the contract matters just as much: nothing may be trusted
 * until the operator names a proxy, or any client could claim to be secure, or
 * to be some other IP -- the daily quota accounting is keyed on the client IP.
 */
class TrustProxiesTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->resetTrustedProxyState();
        config(['app.redirect_https' => true]);
    }

    public function tearDown(): void
    {
        $this->resetTrustedProxyState();

        parent::tearDown();
    }

    /**
     * Trusted proxies are static state on the Symfony request, so they would
     * otherwise leak into every test that runs after this one.
     *
     * @return void
     */
    protected function resetTrustedProxyState(): void
    {
        BaseTrustProxies::flushState();
        Request::setTrustedProxies([], 0);
    }

    /**
     * Run one request through the middleware and report what the application
     * would then see.
     *
     * @param  array<string, string>  $headers
     * @return \Illuminate\Http\Request
     */
    protected function requestAfterMiddleware(array $headers): Request
    {
        $request = Request::create('http://example.com/api/version', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        foreach($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        (new TrustProxies())->handle($request, function($request) {
            return new Response();
        });

        return $request;
    }

    /**
     * The loop this guards against: TLS terminated upstream, proxy configured.
     */
    public function testForwardedProtoFromTrustedProxyPreventsRedirectLoop(): void
    {
        config(['app.trusted_proxies' => '*']);

        $response = $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'https']);

        $response->assertStatus(200);
    }

    /**
     * With no proxy configured the header is just client input and must be
     * ignored, so an actual plain-http request is still redirected.
     */
    public function testForwardedProtoIsIgnoredWhenNoProxyIsTrusted(): void
    {
        config(['app.trusted_proxies' => null]);

        $response = $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'https']);

        $response->assertStatus(302);
        $this->assertSame('https://example.com/auth/reset', $response->headers->get('Location'));
    }

    /**
     * A proxy list that does not include the caller is equivalent to trusting
     * nothing for that caller.
     */
    public function testForwardedProtoIsIgnoredForAnUnlistedPeer(): void
    {
        config(['app.trusted_proxies' => '198.51.100.7']); // TEST-NET-2, not the caller

        $response = $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'https']);

        $response->assertStatus(302);
    }

    /**
     * A spoofed client address must not be adopted when nothing is trusted:
     * the daily quota is counted per client IP.
     */
    public function testForwardedForIsIgnoredWhenNoProxyIsTrusted(): void
    {
        config(['app.trusted_proxies' => null]);

        $request = $this->requestAfterMiddleware(['X-Forwarded-For' => '203.0.113.9']); // TEST-NET-3

        $this->assertSame('127.0.0.1', $request->ip());
        $this->assertFalse($request->isSecure());
    }

    /**
     * Behind a trusted proxy the forwarded address and scheme are the real
     * ones, which is the whole point of configuring it.
     */
    public function testForwardedHeadersAreHonouredBehindATrustedProxy(): void
    {
        config(['app.trusted_proxies' => '127.0.0.1']);

        $request = $this->requestAfterMiddleware([
            'X-Forwarded-For'   => '203.0.113.9',
            'X-Forwarded-Proto' => 'https',
        ]);

        $this->assertSame('203.0.113.9', $request->ip());
        $this->assertTrue($request->isSecure());
    }
}
