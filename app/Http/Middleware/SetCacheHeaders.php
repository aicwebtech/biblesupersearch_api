<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        $action = $this->resolveAction($request);

        if($action === null || !$this->shouldCache($request, $response)) {
            return $response;
        }

        $maxAge = $this->maxAgeForAction($action);

        if($maxAge === null) {
            return $response;
        }

        $this->stripCookies($response);

        if(config('bss.cache_headers.visibility') === 'private') {
            $response->setPrivate();
        }
        else {
            $response->setPublic();
        }

        $response->setMaxAge($maxAge);
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
     * Resolve the API action for the request, or null when this is not an
     * /api/ read request. Mirrors the routes: /api/{action?} and
     * /api/v2/{action?}, both defaulting to 'query'.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function resolveAction(Request $request): ?string
    {
        $segments = explode('/', $request->path());

        if(array_shift($segments) !== 'api') {
            return null; // not an API request
        }

        if(($segments[0] ?? null) === 'v2') {
            array_shift($segments); // remove version segment
        }

        $action = $segments[0] ?? '';

        return ($action === '') ? 'query' : $action;
    }

    /**
     * Return the configured max-age (seconds) for the given action, or null
     * when the action is not configured to be cached.
     *
     * @param  string  $action
     * @return int|null
     */
    protected function maxAgeForAction(string $action): ?int
    {
        $actions = config('bss.cache_headers.actions', []);

        return array_key_exists($action, $actions) ? (int) $actions[$action] : null;
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
