<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HttpsRedirect {

    /**
     * Forwarded-scheme header values that mean https.
     *
     * This is the list Symfony's Request::isSecure() accepts, so the loop guard
     * below recognises exactly those requests that TrustProxies would have
     * treated as secure had the proxy been trusted.
     */
    protected const FORWARDED_HTTPS_VALUES = ['https', 'on', 'ssl', '1'];

    /**
     * How long to suppress repeat proxy-misconfiguration warnings, in seconds.
     *
     * The condition the warning describes holds for every request, so an
     * unthrottled log line would grow at the rate of site traffic.
     */
    protected const PROXY_WARNING_INTERVAL = 3600;

    /**
     * Cache key backing that suppression.
     */
    protected const PROXY_WARNING_KEY = 'https_redirect.untrusted_proxy_warned';

    /**
     * Handle an incoming request.
     *
     * This middleware is global (app/Http/Kernel.php), so it covers /api/* as
     * well as the browser surfaces. A 302 tells the client to re-issue the
     * request as a GET, which would silently drop the body of an API POST, so
     * unsafe methods get 307 -- the method- and body-preserving equivalent of
     * a 302. Safe methods keep 302 so that toggling REDIRECT_HTTPS back off is
     * not defeated by a cached permanent redirect.
     *
     * One request is deliberately not redirected even with the setting on: see
     * isUntrustedForwardedHttps() for why an unconfigured proxy gets a log line
     * rather than a redirect it could never satisfy.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) 
    {
        if (!$request->secure() && config('app.redirect_https') === TRUE) {
            if ($this->isUntrustedForwardedHttps($request)) {
                $this->warnAboutUntrustedProxy();

                return $next($request);
            }

            $status = $request->isMethodSafe() ? 302 : 307;

            $response = redirect()->secure($request->getRequestUri(), $status);

            $this->allowCrossOriginRedirect($request, $response);

            return $response;
        }

        return $next($request);
    }

    /**
     * Would redirecting this request only produce a loop?
     *
     * TLS terminated upstream is the common self-hosted arrangement, and it is
     * invisible to the application until the operator names the proxy in
     * TRUSTED_PROXIES. Until they do, Request::secure() is permanently FALSE:
     * the browser is already on https, this middleware redirects it to https
     * anyway, the proxy forwards the new request over plain http, and the cycle
     * repeats until the browser gives up. The admin cannot reach the login page
     * to correct the setting that caused it, and nothing in the response says
     * what went wrong.
     *
     * So when the request carries a forwarded scheme of https that we are not
     * allowed to believe, skip the redirect rather than emit one that cannot
     * succeed, and tell the operator what to configure.
     *
     * A client is free to forge these headers, but gains nothing by it: the
     * only outcome is being served over the plain http it asked for. This does
     * not make Request::secure() TRUE, it does not affect the Secure attribute
     * on the session cookie (config/session.php reads the config, not the
     * request), and no authorization check consults it. Weighed against locking
     * every correctly-proxied operator out of their own site, that is the
     * better failure.
     *
     * Only reachable when Request::secure() is already FALSE, which means
     * TrustProxies declined to honour whatever is below -- a properly
     * configured proxy never gets this far.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isUntrustedForwardedHttps($request): bool
    {
        $proto = $request->headers->get('X-Forwarded-Proto');

        if ($proto !== NULL && $this->meansHttps(explode(',', $proto)[0])) {
            return TRUE;
        }

        foreach (['X-Forwarded-Ssl', 'Front-End-Https'] as $header) {
            if (strtolower(trim((string) $request->headers->get($header))) === 'on') {
                return TRUE;
            }
        }

        return $this->forwardedHeaderMeansHttps($request->headers->get('Forwarded'));
    }

    /**
     * Read the scheme out of an RFC 7239 Forwarded header.
     *
     * Only the proto parameter matters here, and only its first occurrence --
     * the leftmost element is the one nearest the client, which is the hop that
     * actually spoke to the browser.
     *
     * @param  string|null  $forwarded
     * @return bool
     */
    protected function forwardedHeaderMeansHttps($forwarded): bool
    {
        if ($forwarded === NULL) {
            return FALSE;
        }

        if (!preg_match('/proto\s*=\s*"?([a-z0-9!#$%&\'*+.^_`|~-]+)"?/i', $forwarded, $matches)) {
            return FALSE;
        }

        return $this->meansHttps($matches[1]);
    }

    /**
     * @param  string  $value
     * @return bool
     */
    protected function meansHttps(string $value): bool
    {
        return in_array(strtolower(trim($value)), self::FORWARDED_HTTPS_VALUES, TRUE);
    }

    /**
     * Record the misconfiguration, at most once per PROXY_WARNING_INTERVAL.
     *
     * @return void
     */
    protected function warnAboutUntrustedProxy(): void
    {
        if (!$this->shouldWarnAboutUntrustedProxy()) {
            return;
        }

        Log::warning('REDIRECT_HTTPS is enabled and this request arrived with a forwarded https scheme, but the '
            . 'proxy that sent it is not trusted, so the redirect was skipped to avoid an endless loop. Set '
            . 'TRUSTED_PROXIES to the address of the proxy or load balancer that terminates TLS, or turn '
            . 'REDIRECT_HTTPS off. See config/app.php.');
    }

    /**
     * Cache::add() is atomic, so concurrent requests produce one line rather
     * than one per worker. A cache store that is unavailable must not silence
     * the warning -- an unconfigured proxy is the more urgent problem of the
     * two, and losing the message is how the loop stayed invisible to begin
     * with.
     *
     * @return bool
     */
    protected function shouldWarnAboutUntrustedProxy(): bool
    {
        try {
            return Cache::add(self::PROXY_WARNING_KEY, TRUE, self::PROXY_WARNING_INTERVAL);
        }
        catch (\Exception $e) {
            return TRUE;
        }
    }

    /**
     * Let a cross-origin caller follow the redirect.
     *
     * Browsers run the CORS check against every response in a redirect chain, not
     * just the last one, so a redirect with no Access-Control-Allow-Origin fails an
     * XHR or fetch outright rather than being followed. That matters here because
     * this middleware is global: the API's own header is set by ApiController and
     * ApiAccess, both of which run after routing, and this redirect short-circuits
     * long before either of them.
     *
     * Without this, turning REDIRECT_HTTPS on would break every browser client still
     * pointed at an http:// API URL, rather than quietly upgrading it.
     *
     * The value matches what those two already send. Echoing the origin instead would
     * not help: on a cross-origin redirect the browser replaces Origin with null for
     * the follow-up request (http and https are different origins), and only the
     * wildcard covers both legs.
     *
     * Only requests that actually carry an Origin are CORS requests, so an ordinary
     * browser navigation is left alone.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function allowCrossOriginRedirect($request, $response): void
    {
        if (!$request->headers->has('Origin')) {
            return;
        }

        $response->headers->set('Access-Control-Allow-Origin', '*');
    }
}
