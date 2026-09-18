<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Log;

/**
 * Rate limiting for the installer, which runs before there is a database to lean on.
 *
 * The install routes are unauthenticated by design - first run setup has nobody to
 * authenticate yet - and /install/config/process starts a multi-minute migration, so they
 * do need a limit. The stock 'throttle' middleware cannot supply it: it uses the default
 * cache store, and the point of these routes is that the application is not installed yet.
 * With CACHE_DRIVER=database the cache table does not exist until migrate has run, and a
 * redis or memcached store may be pointing at a service the operator has not configured
 * either - so the limiter itself would 500 the page that exists to fix that.
 * InstallManager::claimInstallLock() refuses a cache lock for exactly this reason.
 *
 * Two departures from the parent follow:
 *
 * 1. The limiter is bound to the filesystem store rather than the configured default. It
 *    needs no migration and no external service, so it is available at the one moment
 *    these routes run.
 * 2. If even that store cannot be read, the request is let through with a warning instead
 *    of failing. An installer nobody can reach is a worse outcome than an unthrottled one,
 *    and the install claim - a file, not a cache entry - still serialises the expensive
 *    endpoint.
 */
class ThrottleInstallRequests extends ThrottleRequests
{
    /**
     * The one store that is usable before the application has been installed.
     */
    protected const CACHE_STORE = 'file';

    /**
     * @param  \Illuminate\Contracts\Cache\Factory  $cache
     */
    public function __construct(protected CacheFactory $cache)
    {
        // parent::__construct() is deliberately not called: building the limiter is what
        // can fail here, and handle() is where that can be dealt with rather than thrown
        // out of the container.
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        if(!$this->bootLimiter()) {
            return $next($request);
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }

    /**
     * Put a working limiter on $this->limiter, or report that there is none to be had.
     *
     * The store is read once rather than merely resolved: resolving a store succeeds for
     * every driver, and a database store with no cache table or an unreachable redis only
     * announces itself on the first operation.
     *
     * @return bool
     */
    protected function bootLimiter(): bool
    {
        try {
            $limiter = new RateLimiter($this->cache->store(static::CACHE_STORE));

            $limiter->attempts('install-throttle-probe');

            $this->limiter = $limiter;

            return TRUE;
        }
        catch(\Throwable $e) {
            Log::warning('Install rate limiting is unavailable, proceeding without it: ' . $e->getMessage());

            return FALSE;
        }
    }
}
