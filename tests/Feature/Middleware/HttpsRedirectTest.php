<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * HttpsRedirect is registered in the global middleware stack (app/Http/Kernel.php)
 * rather than as a per-route alias, so the installer, admin, auth and
 * password-reset surfaces are all covered rather than just the docs controller.
 *
 * The redirect itself stays gated on config('app.redirect_https'), which
 * defaults to FALSE (see Tests\Feature\Config\SecureDefaultsTest), so a
 * plain-http deployment that never set REDIRECT_HTTPS is unaffected.
 *
 * Where TLS is terminated upstream, TrustProxies must run first or
 * Request::secure() is never TRUE and this redirects forever -- see
 * Tests\Feature\Middleware\TrustProxiesTest. When the operator has not yet
 * named that proxy, the middleware recognises the forwarded scheme it is not
 * allowed to trust and declines to redirect rather than producing the loop.
 *
 * Note: requests are issued against an explicit http:// base URL. APP_URL is
 * https in the test environment, so a relative $this->get() would already be
 * secure and the middleware would (correctly) do nothing.
 */
class HttpsRedirectTest extends TestCase
{
    /**
     * Trusted proxies are static state on the Symfony request and survive
     * between tests in the same process, so every test here starts from the
     * plain direct-connection case it assumes.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->trustNoProxies();
    }

    public function tearDown(): void
    {
        $this->trustNoProxies();

        parent::tearDown();
    }

    /**
     * @return void
     */
    protected function trustNoProxies(): void
    {
        BaseTrustProxies::flushState();
        Request::setTrustedProxies([], 0);
        config(['app.trusted_proxies' => null]);
    }
    /**
     * Asserting on the full Location matters: it proves the redirect came from
     * HttpsRedirect (same URI, https scheme) rather than from the auth
     * middleware bouncing an unauthenticated request to /login.
     *
     * @param  string  $path
     * @return void
     */
    protected function assertRedirectsToHttps(string $path): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com' . $path);

        $response->assertStatus(302);
        $this->assertSame('https://example.com' . $path, $response->headers->get('Location'));
    }

    /**
     * Admin routes must be covered, and the redirect must run ahead of the auth
     * middleware so credentials are never submitted over plaintext first.
     */
    public function testAdminRouteIsRedirectedToHttps(): void
    {
        $this->assertRedirectsToHttps('/admin/config');
    }

    /**
     * The installer submits the first administrator password and previously had
     * no https middleware of any kind.
     */
    public function testInstallerRouteIsRedirectedToHttps(): void
    {
        $this->assertRedirectsToHttps('/install');
    }

    public function testPasswordResetRouteIsRedirectedToHttps(): void
    {
        $this->assertRedirectsToHttps('/auth/reset');
    }

    public function testLoginRouteIsRedirectedToHttps(): void
    {
        $this->assertRedirectsToHttps('/login');
    }

    /**
     * With the setting off nothing is forced to https, which is what keeps the
     * middleware inert for plain-http deployments.
     */
    public function testRequestIsNotRedirectedWhenDisabled(): void
    {
        config(['app.redirect_https' => false]);

        $response = $this->get('http://example.com/auth/reset');

        $response->assertStatus(200);
    }

    /**
     * The middleware compares against TRUE identically, so an absent or
     * unparsed setting must also leave the request alone rather than fall
     * through to a truthy comparison.
     */
    public function testRequestIsNotRedirectedWhenSettingIsAbsent(): void
    {
        config(['app.redirect_https' => null]);

        $response = $this->get('http://example.com/auth/reset');

        $response->assertStatus(200);
    }

    /**
     * The middleware is global, so it now covers /api/* as well. A 302 makes the
     * client re-issue the request as a GET, which silently discards an API
     * POST's body; 307 preserves both the method and the body.
     */
    public function testUnsafeMethodIsRedirectedWithoutLosingTheBody(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->post('http://example.com/api/v2/query', ['bible' => 'kjv', 'reference' => 'John 3:16']);

        $response->assertStatus(307);
        $this->assertSame('https://example.com/api/v2/query', $response->headers->get('Location'));
    }

    /**
     * Safe methods keep 302 rather than a permanent redirect, so turning
     * REDIRECT_HTTPS back off is not defeated by a cached 301/308.
     */
    public function testSafeMethodKeepsATemporaryRedirect(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/api/v2/query?bible=kjv&reference=John+3:16');

        $response->assertStatus(302);
    }

    /**
     * A browser runs the CORS check against every response in a redirect chain, so a
     * redirect with no Access-Control-Allow-Origin fails a cross-origin XHR outright
     * instead of being followed. The API's own header comes from ApiController and
     * ApiAccess, both of which run after routing -- this redirect short-circuits first,
     * so without the header here, turning REDIRECT_HTTPS on would break every browser
     * client still pointed at an http:// API URL rather than upgrading it.
     */
    public function testCrossOriginApiRedirectCanBeFollowed(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/api/v2/query?bible=kjv&reference=John+3:16', [
            'Origin' => 'https://someones-site.example',
        ]);

        $response->assertStatus(302);
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * The same on the 307 path: a cross-origin POST has to survive both the method
     * preservation and the CORS check.
     */
    public function testCrossOriginUnsafeMethodRedirectCanBeFollowed(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->post('http://example.com/api/v2/query', ['bible' => 'kjv'], [
            'Origin' => 'https://someones-site.example',
        ]);

        $response->assertStatus(307);
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * The wildcard is what makes the second leg work, too: on a cross-origin redirect
     * the browser sends the follow-up request with Origin: null, which only '*' matches.
     */
    public function testARedirectFollowedWithAnOpaqueOriginIsStillAllowed(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/api/v2/query', ['Origin' => 'null']);

        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Only CORS requests carry an Origin, so an ordinary browser navigation to an admin
     * or login page is not given a header it has no use for.
     */
    public function testARedirectWithoutAnOriginCarriesNoCorsHeader(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/admin/config');

        $response->assertStatus(302);
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * An already-secure request must pass straight through rather than loop.
     */
    public function testSecureRequestIsNotRedirected(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('https://example.com/auth/reset');

        $response->assertStatus(200);
    }

    /**
     * The loop this guards against: TLS is terminated by a proxy the operator
     * has not named in TRUSTED_PROXIES, so Request::secure() is permanently
     * FALSE. Redirecting would send the browser to the https URL it is already
     * on, forever, with no way to reach the admin login and turn the setting
     * off. Serving the request is the recoverable failure.
     */
    public function testForwardedHttpsFromAnUntrustedProxyIsNotRedirected(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'https']);

        $response->assertStatus(200);
    }

    /**
     * The guard is specific to a forwarded scheme that already says https. A
     * proxy reporting plain http is relaying a genuinely insecure request, and
     * redirecting it terminates rather than loops.
     */
    public function testForwardedHttpIsStillRedirected(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'http']);

        $response->assertStatus(302);
        $this->assertSame('https://example.com/auth/reset', $response->headers->get('Location'));
    }

    /**
     * Symfony treats on, ssl and 1 as https alongside the literal scheme, so
     * the guard has to recognise the same set -- otherwise a proxy using one of
     * them loops on exactly the configuration this is meant to rescue.
     *
     * @return array<int, array<int, string>>
     */
    public static function forwardedHttpsValueProvider(): array
    {
        return [['https'], ['on'], ['ssl'], ['1'], ['HTTPS']];
    }

    #[DataProvider('forwardedHttpsValueProvider')]
    public function testEveryForwardedValueMeaningHttpsPreventsTheRedirect(string $value): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => $value]);

        $response->assertStatus(200, $value . ' means https to Symfony, so it must mean https here too');
    }

    /**
     * Only the leftmost hop spoke to the browser. A chain that reached this
     * application over https first is the looping case however the later hops
     * are labelled.
     */
    public function testOnlyTheLeftmostForwardedProtoIsRead(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'https, http']);

        $response->assertStatus(200);
    }

    /**
     * And the reverse: a client that arrived over plain http is redirected even
     * though something further along the chain used https.
     */
    public function testALeftmostPlainHttpHopIsStillRedirected(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'http, https']);

        $response->assertStatus(302);
    }

    /**
     * nginx and Apache configurations in the wild announce the forwarded scheme
     * this way instead.
     */
    public function testForwardedSslHeaderPreventsTheRedirect(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/auth/reset', ['X-Forwarded-Ssl' => 'on']);

        $response->assertStatus(200);
    }

    /**
     * As do older Microsoft front ends.
     */
    public function testFrontEndHttpsHeaderPreventsTheRedirect(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/auth/reset', ['Front-End-Https' => 'on']);

        $response->assertStatus(200);
    }

    /**
     * The standardised RFC 7239 spelling, which carries the scheme as one
     * parameter among several.
     */
    public function testRfc7239ForwardedHeaderPreventsTheRedirect(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/auth/reset', [
            'Forwarded' => 'for=192.0.2.60;proto=https;by=203.0.113.43',
        ]);

        $response->assertStatus(200);
    }

    /**
     * A Forwarded header naming plain http is not the loop case.
     */
    public function testRfc7239ForwardedHeaderForPlainHttpIsStillRedirected(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/auth/reset', ['Forwarded' => 'for=192.0.2.60;proto=http']);

        $response->assertStatus(302);
    }

    /**
     * A Forwarded header that says nothing about the scheme leaves the request
     * exactly as insecure as it looked.
     */
    public function testRfc7239ForwardedHeaderWithoutAProtoIsStillRedirected(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('http://example.com/auth/reset', ['Forwarded' => 'for=192.0.2.60']);

        $response->assertStatus(302);
    }

    /**
     * Skipping the redirect keeps the site reachable but leaves the real
     * problem -- an unconfigured proxy -- in place, and the application still
     * believes it is serving plain http. The operator only finds out if we say
     * so, and the message has to name the setting that fixes it.
     */
    public function testTheSkippedRedirectIsReported(): void
    {
        config(['app.redirect_https' => true]);

        Log::spy();

        $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'https']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function($message) {
                return str_contains($message, 'TRUSTED_PROXIES');
            });
    }

    /**
     * The condition holds for every request, so an unthrottled warning would
     * grow the log at the rate of site traffic -- which on a busy API is its
     * own outage.
     */
    public function testTheProxyWarningIsNotRepeatedForEveryRequest(): void
    {
        config(['app.redirect_https' => true]);

        Log::spy();

        $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'https']);
        $this->get('http://example.com/admin/config', ['X-Forwarded-Proto' => 'https']);
        $this->get('http://example.com/api/v2/version', ['X-Forwarded-Proto' => 'https']);

        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * With redirects off the guard is never reached, so a forwarded header on
     * an ordinary plain-http deployment cannot produce a spurious warning.
     */
    public function testNothingIsReportedWhenRedirectsAreDisabled(): void
    {
        config(['app.redirect_https' => false]);

        Log::spy();

        $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'https']);

        Log::shouldNotHaveReceived('warning');
    }

    /**
     * And once the operator names the proxy, TrustProxies makes the request
     * genuinely secure: no redirect, and nothing left to warn about.
     */
    public function testATrustedProxyProducesNeitherARedirectNorAWarning(): void
    {
        config(['app.redirect_https' => true, 'app.trusted_proxies' => '*']);

        Log::spy();

        $response = $this->get('http://example.com/auth/reset', ['X-Forwarded-Proto' => 'https']);

        $response->assertStatus(200);

        Log::shouldNotHaveReceived('warning');
    }
}
