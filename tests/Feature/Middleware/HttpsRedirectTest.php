<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;

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
 * Tests\Feature\Middleware\TrustProxiesTest.
 *
 * Note: requests are issued against an explicit http:// base URL. APP_URL is
 * https in the test environment, so a relative $this->get() would already be
 * secure and the middleware would (correctly) do nothing.
 */
class HttpsRedirectTest extends TestCase
{
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
     * An already-secure request must pass straight through rather than loop.
     */
    public function testSecureRequestIsNotRedirected(): void
    {
        config(['app.redirect_https' => true]);

        $response = $this->get('https://example.com/auth/reset');

        $response->assertStatus(200);
    }
}
