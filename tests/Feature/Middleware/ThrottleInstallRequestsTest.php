<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use App\Http\Middleware\ThrottleInstallRequests;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * The install routes are the one group that runs before migrate, so the stock throttle
 * middleware cannot serve them: it limits through the default cache store, and with
 * CACHE_DRIVER=database the cache table does not exist yet. The limiter would then 500 the
 * only page that can fix that. InstallManager::claimInstallLock() refuses a cache lock for
 * the same reason.
 */
class ThrottleInstallRequestsTest extends TestCase
{
    /** @var string */
    private $dir;

    public function setUp(): void
    {
        parent::setUp();

        // The limiter writes real entries; they go somewhere disposable rather than into
        // the application's own cache directory.
        $this->dir = sys_get_temp_dir() . '/bss_install_throttle_' . bin2hex(random_bytes(6));
        mkdir($this->dir);

        config(['cache.stores.file.path' => $this->dir]);
        app('cache')->forgetDriver('file');
    }

    public function tearDown(): void
    {
        app('cache')->forgetDriver('file');

        $this->removeDirectory($this->dir);

        parent::tearDown();
    }

    public function testTheLimiterDoesNotUseTheDefaultCacheStore(): void
    {
        // Whatever the application is configured to use normally, which during an install
        // may be a database table that does not exist yet or a service nobody has set up.
        config(['cache.default' => 'array']);

        $middleware = app(ThrottleInstallRequests::class);

        $response = $middleware->handle($this->request('/install/check'), fn($request) => new Response('ok'), 20, 1);

        $this->assertSame('ok', $response->getContent());
        $this->assertInstanceOf(
            FileStore::class,
            $this->limiterStore($middleware),
            'The install limiter must be bound to the filesystem store, not the configured default'
        );
    }

    /**
     * Fail-open, not fail-closed: an installer nobody can reach is a worse outcome than an
     * unthrottled one, and /install/config/process is serialised by the install claim on
     * disk regardless.
     */
    public function testAnUnusableCacheStoreLetsTheInstallerThrough(): void
    {
        $cache = \Mockery::mock(\Illuminate\Contracts\Cache\Factory::class);
        $cache->shouldReceive('store')->andThrow(new \RuntimeException('no cache table'));

        $middleware = new ThrottleInstallRequests($cache);

        $response = $middleware->handle($this->request('/install/check'), fn($request) => new Response('ok'), 20, 1);

        $this->assertSame('ok', $response->getContent());
    }

    /**
     * A store that resolves but cannot be used is the CACHE_DRIVER=database case, and it
     * only announces itself on the first operation -- which is why the middleware
     * exercises the store rather than settling for one it could merely resolve.
     */
    public function testAStoreThatResolvesButCannotBeUsedLetsTheInstallerThrough(): void
    {
        $repository = \Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
        $repository->shouldReceive('add')->andThrow(new \RuntimeException('Base table or view not found: cache'));

        $cache = \Mockery::mock(\Illuminate\Contracts\Cache\Factory::class);
        $cache->shouldReceive('store')->andReturn($repository);

        $middleware = new ThrottleInstallRequests($cache);

        $response = $middleware->handle($this->request('/install/check'), fn($request) => new Response('ok'), 20, 1);

        $this->assertSame('ok', $response->getContent());
    }

    /**
     * The probe has to write, not just read. A fresh upload whose cache directory is not
     * writable by the web server user answers a read with "nothing cached" and no error,
     * so a read-only probe would hand parent::handle() a limiter that 500s on its first
     * RateLimiter::hit() -- every install route down, which is precisely what the
     * fail-open path exists to prevent.
     */
    public function testAStoreThatReadsButCannotWriteLetsTheInstallerThrough(): void
    {
        $repository = \Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
        $repository->shouldReceive('get')->andReturn(null);
        $repository->shouldReceive('add')->andThrow(new \RuntimeException('Unable to create lockable file'));

        $cache = \Mockery::mock(\Illuminate\Contracts\Cache\Factory::class);
        $cache->shouldReceive('store')->andReturn($repository);

        $middleware = new ThrottleInstallRequests($cache);

        $response = $middleware->handle($this->request('/install/check'), fn($request) => new Response('ok'), 20, 1);

        $this->assertSame('ok', $response->getContent());
    }

    /**
     * The same case against a real file store rather than a mock: the configured cache
     * path cannot be created because part of it is an ordinary file.
     *
     * The error handler mirrors the one Laravel installs for real requests
     * (Illuminate\Foundation\Bootstrap\HandleExceptions), which turns the filesystem's
     * warnings into ErrorException. PHPUnit replaces that handler with its own, so without
     * this the test would exercise a failure mode the application never actually sees.
     */
    public function testAnUnwritableCacheDirectoryLetsTheInstallerThrough(): void
    {
        $blocker = $this->dir . '/blocker';
        file_put_contents($blocker, 'not a directory');

        config(['cache.stores.file.path' => $blocker . '/cache']);
        app('cache')->forgetDriver('file');

        set_error_handler(function($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $middleware = app(ThrottleInstallRequests::class);

            $response = $middleware->handle($this->request('/install/check'), fn($request) => new Response('ok'), 20, 1);
        }
        finally {
            restore_error_handler();
        }

        $this->assertSame('ok', $response->getContent());
    }

    /**
     * The probe must not spend one of the attempts it is checking for.
     */
    public function testTheProbeDoesNotCountAgainstTheLimit(): void
    {
        $middleware = app(ThrottleInstallRequests::class);
        $next = fn($request) => new Response('ok');

        $response = $middleware->handle($this->request('/install/config'), $next, 3, 1);

        $this->assertSame('3', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('2', $response->headers->get('X-RateLimit-Remaining'), 'only the request itself may be counted');
    }

    /**
     * The limit still has to be a limit: these endpoints are unauthenticated and one of
     * them starts a multi-minute migration.
     */
    public function testRequestsBeyondTheLimitAreRejected(): void
    {
        $middleware = app(ThrottleInstallRequests::class);
        $next = fn($request) => new Response('ok');

        for($i = 0; $i < 3; $i++) {
            $this->assertSame('ok', $middleware->handle($this->request('/install/config'), $next, 3, 1)->getContent());
        }

        $this->expectException(ThrottleRequestsException::class);

        $middleware->handle($this->request('/install/config'), $next, 3, 1);
    }

    /**
     * A request carrying the route the throttle needs to build its signature from.
     *
     * @param  string  $uri
     * @return \Illuminate\Http\Request
     */
    protected function request(string $uri): Request
    {
        $request = Request::create($uri, 'POST');

        $request->setRouteResolver(function() use ($uri) {
            return new Route('POST', $uri, []);
        });

        return $request;
    }

    /**
     * The cache store the middleware actually ended up limiting through.
     *
     * @param  \App\Http\Middleware\ThrottleInstallRequests  $middleware
     * @return \Illuminate\Contracts\Cache\Store
     */
    protected function limiterStore(ThrottleInstallRequests $middleware)
    {
        $limiter = (new \ReflectionProperty(ThrottleRequests::class, 'limiter'))->getValue($middleware);
        $repository = (new \ReflectionProperty(RateLimiter::class, 'cache'))->getValue($limiter);

        return $repository->getStore();
    }

    /**
     * @param  string  $dir
     * @return void
     */
    protected function removeDirectory(string $dir): void
    {
        if(!is_dir($dir)) {
            return;
        }

        foreach(glob($dir . '/*') ?: [] as $path) {
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
