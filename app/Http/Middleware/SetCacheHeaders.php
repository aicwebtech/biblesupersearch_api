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

        if(!$this->shouldCache($request, $response)) {
            return $response;
        }

        $maxAge = $this->maxAgeForRequest($request);

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

        if($request->input('callback')) {
            return false; // JSONP responses are not cacheable
        }

        $segments = explode('/', $request->path());

        return ($segments[0] ?? null) === 'api';
    }

    /**
     * Resolve the API action for the request and return its configured max-age,
     * or null when the action is not configured to be cached.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return int|null
     */
    protected function maxAgeForRequest(Request $request): ?int
    {
        $segments = explode('/', $request->path());
        array_shift($segments); // remove 'api'

        if(($segments[0] ?? null) === 'v2') {
            array_shift($segments); // remove version segment
        }

        $action = $segments[0] ?? 'query';
        $action = ($action === '') ? 'query' : $action;

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
