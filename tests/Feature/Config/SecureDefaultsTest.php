<?php

namespace Tests\Feature\Config;

use Tests\TestCase;

/**
 * HttpsRedirect is global and the session cookie is issued on every admin
 * login, so the *defaults* of these two settings decide what happens to an
 * existing install whose .env predates them. Both previously defaulted to on,
 * which made a plain-http deployment either unreachable (endless redirect) or
 * unable to log in (the browser drops a Secure cookie over http).
 *
 * These assert the shipped default rather than the value this machine's .env
 * happens to carry, so the config file is evaluated with the relevant env keys
 * forced to a known state.
 *
 * That state is driven through $_SERVER/$_ENV/putenv rather than the Dotenv
 * repository, matching Tests\Feature\Providers\BroadcastServiceProviderTest:
 * env() reads through whichever adapter answers first, and a repository write
 * is not guaranteed to be honoured for a key the environment already defines.
 */
class SecureDefaultsTest extends TestCase
{
    /**
     * Evaluate a config file with the given env keys forced.
     *
     * @param  string  $file    Config file name, without extension
     * @param  array<string, string|null>  $env  Key => value, NULL to unset
     * @return array
     */
    protected function configWithEnv(string $file, array $env): array
    {
        $restore = [];

        foreach($env as $key => $value) {
            $restore[$key] = $_SERVER[$key] ?? $_ENV[$key] ?? (getenv($key) === FALSE ? NULL : getenv($key));

            $this->putEnvEverywhere($key, $value);
        }

        try {
            foreach($env as $key => $value) {
                $this->assertSame(
                    $value,
                    $value === NULL ? (getenv($key) === FALSE ? NULL : getenv($key)) : (string) getenv($key),
                    $key . ' could not be forced for this test; the assertion below would be meaningless'
                );
            }

            return require base_path('config/' . $file . '.php');
        }
        finally {
            foreach($restore as $key => $value) {
                $this->putEnvEverywhere($key, $value);
            }
        }
    }

    /**
     * Set or unset an environment variable across every source env() reads.
     *
     * @param  string  $key
     * @param  string|null  $value  NULL unsets
     * @return void
     */
    protected function putEnvEverywhere(string $key, ?string $value): void
    {
        if($value === NULL) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);

            return;
        }

        $_SERVER[$key] = $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }

    /**
     * An install that never opted in must not have every request redirected.
     */
    public function testRedirectHttpsDefaultsToOff(): void
    {
        $config = $this->configWithEnv('app', ['REDIRECT_HTTPS' => NULL]);

        $this->assertFalse((bool) $config['redirect_https'], 'REDIRECT_HTTPS must default to off');
    }

    /**
     * Nothing is trusted until the operator names a proxy, otherwise a spoofed
     * X-Forwarded-For/Proto would be believed.
     */
    public function testNoProxiesAreTrustedByDefault(): void
    {
        $config = $this->configWithEnv('app', ['TRUSTED_PROXIES' => NULL]);

        $this->assertNull($config['trusted_proxies'], 'TRUSTED_PROXIES must default to trusting nothing');
    }

    /**
     * The secure cookie follows the operator's stated intent about TLS, not
     * APP_ENV: a production install served over plain http must still be able
     * to hold a session.
     */
    public function testSessionCookieIsNotSecureByDefault(): void
    {
        $config = $this->configWithEnv('session', [
            'SESSION_SECURE_COOKIE' => NULL,
            'REDIRECT_HTTPS'        => NULL,
            'APP_ENV'               => 'production',
        ]);

        $this->assertFalse((bool) $config['secure'], 'SESSION_SECURE_COOKIE must not default to on');
    }

    /**
     * Opting into https does opt the cookie in, so a TLS-only install keeps the
     * protection the previous default was reaching for.
     */
    public function testSessionCookieIsSecureWhenHttpsIsForced(): void
    {
        $config = $this->configWithEnv('session', [
            'SESSION_SECURE_COOKIE' => NULL,
            'REDIRECT_HTTPS'        => 'true',
        ]);

        $this->assertTrue((bool) $config['secure'], 'REDIRECT_HTTPS=true must mark the cookie Secure');
    }

    /**
     * HttpsRedirect tests config('app.redirect_https') === TRUE, so anything
     * other than a real bool leaves the redirect off. Laravel's Env::getOption()
     * only converts 'true'/'false'/'null'/'empty', so REDIRECT_HTTPS=1 would
     * otherwise arrive as the string "1": no redirect, yet truthy enough to mark
     * the session cookie Secure -- an admin login that bounces with no error.
     */
    public function testTruthyRedirectHttpsValuesBecomeBoolTrue(): void
    {
        foreach(['true', 'TRUE', '1', 'yes', 'on'] as $value) {
            $config = $this->configWithEnv('app', ['REDIRECT_HTTPS' => $value]);

            $this->assertTrue(
                $config['redirect_https'],
                'REDIRECT_HTTPS=' . $value . ' must normalise to bool TRUE, not "' . $value . '"'
            );
        }
    }

    public function testFalsyRedirectHttpsValuesBecomeBoolFalse(): void
    {
        foreach(['false', 'FALSE', '0', 'no', 'off', ''] as $value) {
            $config = $this->configWithEnv('app', ['REDIRECT_HTTPS' => $value]);

            $this->assertFalse(
                $config['redirect_https'],
                'REDIRECT_HTTPS=' . $value . ' must normalise to bool FALSE'
            );
        }
    }

    /**
     * The two settings must never disagree: a Secure cookie without the matching
     * redirect is what drops the session on a plain-http install.
     */
    public function testSessionCookieAgreesWithRedirectHttpsForEveryForm(): void
    {
        foreach(['true', '1', 'yes', 'on', 'false', '0', 'no', 'off'] as $value) {
            $app = $this->configWithEnv('app', ['REDIRECT_HTTPS' => $value]);

            $session = $this->configWithEnv('session', [
                'SESSION_SECURE_COOKIE' => NULL,
                'REDIRECT_HTTPS'        => $value,
            ]);

            $this->assertSame(
                $app['redirect_https'],
                $session['secure'],
                'REDIRECT_HTTPS=' . $value . ' must mean the same thing to both settings'
            );
        }
    }

    /**
     * An explicit setting still wins over the REDIRECT_HTTPS fallback, in both
     * directions.
     */
    public function testExplicitSessionSecureCookieWins(): void
    {
        $forced_on = $this->configWithEnv('session', [
            'SESSION_SECURE_COOKIE' => 'true',
            'REDIRECT_HTTPS'        => 'false',
        ]);

        $forced_off = $this->configWithEnv('session', [
            'SESSION_SECURE_COOKIE' => 'false',
            'REDIRECT_HTTPS'        => 'true',
        ]);

        $this->assertTrue((bool) $forced_on['secure'], 'an explicit true must win');
        $this->assertFalse((bool) $forced_off['secure'], 'an explicit false must win');
    }
}
