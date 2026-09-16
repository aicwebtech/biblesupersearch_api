<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Helpers;

/**
 * Helpers::resolveApiAction() reads the action out of an API request path, for both route
 * shapes - '/api/{action?}' and '/api/v{version}/{action?}'.
 *
 * Two middlewares depend on it and must agree: ApiAccess decides from the action whether a
 * request is rate limited and whether the caller's access level permits it, SetCacheHeaders
 * decides how long the response may be cached. They used to parse the path separately, and
 * SetCacheHeaders recognized only the literal 'v2' - so every '/api/v3/...' request resolved
 * to the action 'v3', matched nothing in bss.cache_headers.actions, and was never cached.
 *
 * Pure: string in, string out, no application and no request object.
 */
class ResolveApiActionTest extends TestCase
{
    #[DataProvider('apiPathDataProvider')]
    public function testResolveApiAction(string $path, ?string $expected): void
    {
        $this->assertSame($expected, Helpers::resolveApiAction($path));
    }

    public static function apiPathDataProvider(): array
    {
        return [
            // Unversioned route.
            'action'                 => ['api/bibles',           'bibles'],
            'explicit query'         => ['api/query',            'query'],
            'underscored action'     => ['api/statics_changed',  'statics_changed'],
            // Versioned route. Every version, not just the one that existed when this was
            // first written - that is the bug this replaced.
            'v2 action'              => ['api/v2/bibles',        'bibles'],
            'v3 action'              => ['api/v3/bibles',        'bibles'],
            'v3 query'               => ['api/v3/query',         'query'],
            'two digit version'      => ['api/v10/statics',      'statics'],
            'unreleased version'     => ['api/v4/books',         'books'],
            // The action is optional on both routes and defaults to 'query', as the routes do.
            'bare api'               => ['api',                  'query'],
            'bare version'           => ['api/v2',               'query'],
            'bare v3'                => ['api/v3',               'query'],
            // 'version' is an action, not version 'ersion' - the digits are what make a
            // segment a version, and ApiController::versionedAction() disambiguates it too.
            'version action'         => ['api/version',          'version'],
            'versioned version'      => ['api/v3/version',       'version'],
            // Not an API request at all.
            'documentation'          => ['docs',                 NULL],
            'root'                   => ['/',                    NULL],
            'empty'                  => ['',                     NULL],
            'admin'                  => ['admin/bibles',         NULL],
            // A path that merely starts with the letters 'api'.
            'api prefixed segment'   => ['apidocs/bibles',       NULL],
        ];
    }

    /** Request::path() never carries one, but a stray slash must not produce an empty action. */
    #[DataProvider('trailingSlashDataProvider')]
    public function testATrailingSlashStillResolvesToTheDefaultAction(string $path): void
    {
        $this->assertSame('query', Helpers::resolveApiAction($path));
    }

    public static function trailingSlashDataProvider(): array
    {
        return [
            'unversioned' => ['api/'],
            'versioned'   => ['api/v3/'],
            'leading too' => ['/api/'],
        ];
    }

    /** A leading slash is accepted, so a caller need not know what Request::path() trims. */
    public function testALeadingSlashIsAccepted(): void
    {
        $this->assertSame('bibles', Helpers::resolveApiAction('/api/v3/bibles'));
        $this->assertSame('bibles', Helpers::resolveApiAction('/api/bibles'));
    }

    public function testNullPathIsNotAnApiRequest(): void
    {
        $this->assertNull(Helpers::resolveApiAction(NULL));
    }

    /** Extra segments past the action belong to the action, not to the resolution. */
    public function testOnlyTheFirstSegmentAfterTheVersionIsTheAction(): void
    {
        $this->assertSame('bibles', Helpers::resolveApiAction('api/v3/bibles/extra/segments'));
        $this->assertSame('bibles', Helpers::resolveApiAction('api/bibles/extra'));
    }

    /** The default is named once, so the routes and both middlewares cannot drift from it. */
    public function testTheDefaultActionMatchesTheConstant(): void
    {
        $this->assertSame(Helpers::DEFAULT_API_ACTION, Helpers::resolveApiAction('api'));
        $this->assertSame('query', Helpers::DEFAULT_API_ACTION);
    }
}
