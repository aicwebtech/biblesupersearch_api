<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers;

/*
 * Sends cacheable response headers (Cache-Control + ETag) on the public,
 * idempotent (GET) read endpoints, and strips session/CSRF cookies from those
 * responses so browsers and shared caches will actually cache them.
 *
 * Registered as the outermost global middleware so it runs after StartSession /
 * AddQueuedCookiesToResponse have attached cookies on the response phase.
 */
class SetCacheHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $action = Helpers::resolveApiAction($request->path());

        if($action === null || !$this->shouldCache($request, $response)) {
            return $response;
        }

        $policy = $this->cachePolicyForAction($action);

        if($policy === null) {
            return $response;
        }

        $this->stripCookies($response);

        if($policy['visibility'] === 'private') {
            $response->setPrivate();
        }
        else {
            $response->setPublic();
        }

        $response->setMaxAge($policy['max_age']);
        $response->setEtag(md5((string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    /**
     * Determine whether this request/response is an eligible public API read.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    protected function shouldCache(Request $request, Response $response): bool
    {
        if(!config('bss.cache_headers.enable')) {
            return false;
        }

        if(!$request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }

        // Only cache JSON API payloads. This excludes HTML responses such as the
        // pretty-printed error view (which is a 200) and JSONP/JS responses.
        if(!str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
            return false;
        }

        // Mirror ApiController's JSONP trigger (array_key_exists('callback', ...)):
        // has() is true whenever the key is present, even for falsy values like '0' or ''.
        if($request->has('callback')) {
            return false; // JSONP responses are not cacheable
        }

        return true;
    }

    /**
     * Return the caching policy for the given action, or null when the action is not
     * configured to be cached.
     *
     * An action is configured either as a bare max-age or as an array carrying a
     * visibility of its own. A per-action visibility may only narrow the configured
     * default, never widen it: API_CACHE_HEADERS_VISIBILITY=private is an operator kill
     * switch, and a per-action 'public' must not reopen what it closed.
     *
     * 'statics' is the action that needs this. Its response embeds the caller's own
     * access/quota state, bucketed by IP and Origin/Referer, so a shared cache keying on
     * the URL alone would serve one caller's quota state to another.
     *
     * @param  string  $action
     * @return array{max_age: int, visibility: string}|null
     */
    protected function cachePolicyForAction(string $action): ?array
    {
        $actions = config('bss.cache_headers.actions', []);

        if(!array_key_exists($action, $actions)) {
            return null;
        }

        $config  = $actions[$action];
        $default = config('bss.cache_headers.visibility');

        if(is_array($config)) {
            $maxAge     = (int) ($config['max_age'] ?? 0);
            $visibility = $config['visibility'] ?? $default;
        }
        else {
            $maxAge     = (int) $config;
            $visibility = $default;
        }

        return [
            'max_age'    => $maxAge,
            'visibility' => ($default === 'private' || $visibility === 'private') ? 'private' : 'public',
        ];
    }

    /**
     * Remove any cookies (e.g. laravel_session, XSRF-TOKEN) attached to the
     * response so it is treated as cacheable.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function stripCookies(Response $response): void
    {
        foreach($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }
    }
}
