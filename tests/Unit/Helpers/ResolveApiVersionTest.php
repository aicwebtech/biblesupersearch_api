<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Helpers;

/**
 * Helpers::resolveApiVersion() reads the version out of an API request path.
 *
 * The companion to resolveApiAction(): that one skips the version segment to reach the
 * action, this one returns the segment it skipped, so the two agree by construction about
 * what counts as a version.
 *
 * ApiAccess decides from it whether a request is billable - a version outside
 * app.api_version_list is answered 404 or 410 without reaching an engine, and must not spend
 * one of the caller's daily hits.
 *
 * Pure: string in, string out, no application and no request object.
 */
class ResolveApiVersionTest extends TestCase
{
    #[DataProvider('apiPathDataProvider')]
    public function testResolveApiVersion(string $path, ?string $expected): void
    {
        $this->assertSame($expected, Helpers::resolveApiVersion($path));
    }

    public static function apiPathDataProvider(): array
    {
        return [
            // Versioned route. Advertised or not - whether the application serves the version
            // is the caller's question, not this one's.
            'v2 action'            => ['api/v2/bibles',   'v2'],
            'v3 action'            => ['api/v3/query',    'v3'],
            'two digit version'    => ['api/v10/statics', 'v10'],
            'unreleased version'   => ['api/v4/books',    'v4'],
            'retired version'      => ['api/v1/query',    'v1'],
            // The action is optional, and its absence does not hide the version.
            'bare version'         => ['api/v3',          'v3'],
            // Unversioned route - the legacy shape, which names no version at all.
            'action'               => ['api/bibles',      NULL],
            'bare api'             => ['api',             NULL],
            // 'version' is an action, not version 'ersion' - the digits are what make a
            // segment a version, and the versioned route constrains {version} to digits too.
            'version action'       => ['api/version',     NULL],
            'versioned version'    => ['api/v3/version',  'v3'],
            'digits not leading'   => ['api/v3x/query',   NULL],
            'no digits'            => ['api/v/query',     NULL],
            // Not an API request at all.
            'documentation'        => ['docs',            NULL],
            'root'                 => ['/',               NULL],
            'empty'                => ['',                NULL],
            'admin'                => ['admin/bibles',    NULL],
            'api prefixed segment' => ['apidocs/v3/x',    NULL],
        ];
    }

    /** A leading or trailing slash is accepted, so a caller need not know what path() trims. */
    #[DataProvider('slashDataProvider')]
    public function testSurroundingSlashesAreAccepted(string $path, ?string $expected): void
    {
        $this->assertSame($expected, Helpers::resolveApiVersion($path));
    }

    public static function slashDataProvider(): array
    {
        return [
            'leading'     => ['/api/v3/bibles', 'v3'],
            'trailing'    => ['api/v3/',        'v3'],
            'both'        => ['/api/v3/',       'v3'],
            'bare api'    => ['/api/',          NULL],
        ];
    }

    public function testNullPathIsNotAnApiRequest(): void
    {
        $this->assertNull(Helpers::resolveApiVersion(NULL));
    }

    /**
     * The two resolvers must not disagree about where the version ends and the action begins:
     * whenever this one finds a version, that one has skipped the same segment.
     */
    #[DataProvider('agreementDataProvider')]
    public function testTheTwoResolversAgreeOnTheSegmentBoundary(string $path, ?string $version, ?string $action): void
    {
        $this->assertSame($version, Helpers::resolveApiVersion($path));
        $this->assertSame($action, Helpers::resolveApiAction($path));
    }

    public static function agreementDataProvider(): array
    {
        return [
            'versioned action'   => ['api/v3/bibles',  'v3', 'bibles'],
            'versioned default'  => ['api/v3',         'v3', 'query'],
            'version action'     => ['api/version',    NULL, 'version'],
            'unversioned action' => ['api/bibles',     NULL, 'bibles'],
            'unversioned default'=> ['api',            NULL, 'query'],
            'not an api request' => ['docs',           NULL, NULL],
        ];
    }
}
