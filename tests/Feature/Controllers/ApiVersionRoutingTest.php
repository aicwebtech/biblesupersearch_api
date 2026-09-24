<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Factories\EngineFactory;
use App\Http\Controllers\ApiController;
use App\Http\Middleware\ApiAccess;

/**
 * BSS-290: the /api/v{version}/{action?} routes, which pick the engine that answers a request
 * instead of every request sharing one.
 *
 * Two things have to hold together: the controller rejects any version the application does
 * not advertise (the factory turns a version straight into a class name, so an unchecked one
 * names a class that does not exist), and the version a response reports is the engine's own
 * rather than the application default - a v2 client must keep seeing 'v2' now that
 * config('app.api_version') has moved to 'v3'.
 *
 * Read-only against installed content.
 */
class ApiVersionRoutingTest extends TestCase
{
    protected $config_cache;
    protected $config_value = 1;
    protected $config_changed = false;

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->config_cache = config('bss.public_access');
        $this->config_changed = false;

        if($this->config_cache != $this->config_value) {
            config(['bss.public_access' => $this->config_value]);
            $this->config_changed = true;
        }
    }

    public function tearDown() :void
    {
        if($this->config_changed) {
            config(['bss.public_access' => $this->config_cache]);
        }

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Version routing
    // -----------------------------------------------------------------------

    public function testTheVersionedRouteServesEveryAdvertisedVersion()
    {
        foreach(config('app.api_version_list') as $vv) {
            $version = ltrim($vv, 'v');

            $response = $this->getJson('/api/v' . $version . '/version');

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(200);
            $this->assertEquals(0, $response['error_level'], $vv);
            $this->assertEquals($vv, $response['results']['api_version'], $vv);
        }
    }

    public function testTheVersionedRouteAcceptsPost()
    {
        $response = $this->postJson('/api/v3/version');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $this->assertEquals('v3', $response['results']['api_version']);
    }

    /**
     * An unadvertised version must be turned away before it reaches the factory, which would
     * otherwise build a class name for an engine that was never written.
     */
    public function testAnUnsupportedVersionIs404()
    {
        $response = $this->get('/api/v9/version');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(404);
        $this->assertIsTheApiErrorEnvelope($response, 'API version not found: v9');

        $this->assertFalse(class_exists(EngineFactory::getClassName(9)));
    }

    public function testAnUnsupportedVersionIs404OnPostAndForEveryAction()
    {
        $this->post('/api/v9/version')->assertStatus(404);
        $this->get('/api/v9/bibles')->assertStatus(404);
        $this->get('/api/v9')->assertStatus(404);
        // Non-numeric versions must not slip through the list check either.
        $this->get('/api/v2x/version')->assertStatus(404);
    }

    public function testAnUnknownActionOnAVersionedRouteIs404()
    {
        $response = $this->get('/api/v3/no_such_action');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(404);
        $this->assertIsTheApiErrorEnvelope($response, 'Action not found');
    }

    /** The action is optional on the versioned route and defaults to 'query', as elsewhere. */
    public function testTheVersionedRouteDefaultsToTheQueryAction()
    {
        $response = $this->getJson('/api/v3');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);
        $this->assertEquals(4, $response['error_level']);
        $this->assertContains(__('errors.no_query'), $response['errors']);

        $response = $this->getJson('/api/v3?request=faith&bible=kjv');
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);
    }

    /**
     * The versioned route is '/api/v{version}', so unconstrained it would claim every path
     * whose first segment begins with 'v' - '/api/version' included, read as version
     * 'ersion'. The route constrains {version} to digits, so this falls through to the
     * generic route and is served as the v2 'version' action rather than answering
     * 'API version not found: version'.
     */
    public function testTheVersionActionIsNotMistakenForAVersion()
    {
        $response = $this->getJson('/api/version');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('v2', $response['results']['api_version']);

        $this->postJson('/api/version')->assertStatus(200);
    }

    /**
     * No action name can collide with the versioned route, whatever it is called. The digit
     * constraint on {version} is what guarantees it: a first segment beginning with 'v' but
     * not spelling a version is not a version, so it reaches the generic route and is
     * answered by the action check there.
     *
     * Every one of these would have bound as a version before the constraint and answered
     * 404 'API version not found' - a real action named 'v...' would have been unreachable.
     */
    #[DataProvider('nonVersionSegmentDataProvider')]
    public function testASegmentThatIsNotAVersionReachesTheGenericRoute(string $path)
    {
        $response = $this->getJson($path);

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(404);
        $this->assertEquals(['Action not found'], $response['errors']);
    }

    public static function nonVersionSegmentDataProvider(): array
    {
        return [
            'word beginning with v' => ['/api/verses'],
            'digits after a word'   => ['/api/verses2'],
            'v then a word'         => ['/api/vbibles'],
            'version with a suffix' => ['/api/v2x'],
            'bare v'                => ['/api/v'],
        ];
    }

    /**
     * The disambiguation the controller used to do by hand: '/api/version/query' named an
     * action on a route that has no such shape, and was answered with a full v2 'version'
     * payload. It is a 404 now - '/api/version' is the endpoint.
     */
    public function testAnActionSegmentFollowedByAnotherSegmentIsNotFound()
    {
        $response = $this->getJson('/api/version/query');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(404);
    }

    /**
     * A version at or below the end-of-life mark is a 410 rather than the 404 an unknown
     * version gets - the client is told the version existed and is gone, not that it was
     * never a version at all.
     */
    public function testAnEndOfLifeVersionIs410()
    {
        $response = $this->get('/api/v1/version');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(410);
        $this->assertIsTheApiErrorEnvelope($response, 'API version is End of Life and no longer supported: v1');

        $this->post('/api/v1/query')->assertStatus(410);
    }

    // -----------------------------------------------------------------------
    // Rate limiting
    // -----------------------------------------------------------------------

    /**
     * A version the application does not serve must not spend one of the caller's daily hits.
     *
     * The versioned route accepts any digits, so ApiAccess now runs before the version is
     * checked - where '/api/v1/query' used to match no route at all and 404 at routing,
     * ahead of every middleware. Without this, a client hardcoded to a retired or typo'd
     * prefix spends its whole daily allowance on 404s and 410s that never reached an engine,
     * and is then rate limited out of its real traffic.
     *
     * Asserted on the decision rather than by calling the endpoint twice and comparing
     * 'hits': the daily counter is a shared row and the suite runs under paratest, so a
     * behavioral assertion here would be flaky - see ApiControllerTest::
     * testTheAccessActionIsFree().
     *
     * @return void
     */
    #[DataProvider('billableRequestDataProvider')]
    public function testOnlyAServedVersionIsBillable(string $path, bool $expected)
    {
        $this->assertSame($expected, ApiAccess::isBillableRequest($path), $path);
    }

    public static function billableRequestDataProvider(): array
    {
        return [
            // Served versions, paid actions.
            'v2 query'             => ['api/v2/query',   TRUE],
            'v3 query'             => ['api/v3/query',   TRUE],
            'v3 default action'    => ['api/v3',         TRUE],
            'v3 bibles'            => ['api/v3/bibles',  TRUE],
            // The legacy unversioned route names no version and is served, so it is billable
            // exactly as it was before the versioned route existed.
            'unversioned query'    => ['api/query',      TRUE],
            'unversioned default'  => ['api',            TRUE],
            'unversioned bibles'   => ['api/bibles',     TRUE],
            // Versions that are never answered - 410 retired, 404 unknown.
            'retired v1'           => ['api/v1/query',   FALSE],
            'unknown v0'           => ['api/v0/query',   FALSE],
            'unknown v99'          => ['api/v99/query',  FALSE],
            'unknown, no action'   => ['api/v99',        FALSE],
            'unknown, any action'  => ['api/v99/bibles', FALSE],
            // Free by configuration, on every route shape.
            'free action'          => ['api/access',     FALSE],
            'free action, v3'      => ['api/v3/access',  FALSE],
            'free action, unknown' => ['api/v99/access', FALSE],
        ];
    }

    /** Every advertised version is billable, whatever the list is configured to hold. */
    public function testEveryAdvertisedVersionIsBillable()
    {
        foreach(config('app.api_version_list') as $vv) {
            $this->assertTrue(ApiAccess::isBillableRequest('api/' . $vv . '/query'), $vv);
        }
    }

    /**
     * The decision follows the configured list rather than a hardcoded set of versions, so a
     * version retired tomorrow stops being billable the moment it leaves the list.
     */
    public function testBillabilityFollowsTheConfiguredVersionList()
    {
        $this->assertTrue(ApiAccess::isBillableRequest('api/v2/query'));

        config(['app.api_version_list' => ['v3']]);

        $this->assertFalse(ApiAccess::isBillableRequest('api/v2/query'));
        $this->assertTrue(ApiAccess::isBillableRequest('api/v3/query'));
        $this->assertTrue(ApiAccess::isBillableRequest('api/query'));
    }

    /**
     * The end-of-life check is floored at v1 deliberately: there was never a v0, so it is an
     * unknown version rather than a retired one and gets the 404, not the 410.
     */
    public function testAVersionBelowTheEndOfLifeMarkIsStill404()
    {
        $response = $this->get('/api/v0/version');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(404);
        $this->assertIsTheApiErrorEnvelope($response, 'API version not found: v0');
    }

    /**
     * The end-of-life comparison is on integers. As strings 'v2' <= 'v10' is false, so a
     * lexical check would answer 404 'never existed' for a version that had been retired.
     */
    public function testTheEndOfLifeCheckIsNotALexicalComparison()
    {
        config(['app.api_version_eol' => 'v10']);
        config(['app.api_version_list' => ['v11']]);

        $response = $this->get('/api/v2/version');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(410);
        $this->assertIsTheApiErrorEnvelope($response, 'API version is End of Life and no longer supported: v2');

        // The boundary itself is retired; the version above it is simply unknown.
        $this->get('/api/v10/version')->assertStatus(410);
        $this->get('/api/v12/version')->assertStatus(404);
    }

    /**
     * Removes a config key outright, the way a config cache built before the key existed
     * would not have it.
     *
     * config([$key => NULL]) stores a NULL instead, which is a different thing: config()
     * answers NULL for a key that is present and null, and only falls back to its default
     * when the key is missing. Both cases matter here, so the two are set up differently.
     */
    private function forgetConfig(string $key): void
    {
        [$file, $inner] = explode('.', $key, 2);

        $values = config($file);
        unset($values[$inner]);

        config([$file => $values]);
    }

    /**
     * 'api_version_eol' is new in BSS-290, so a deployment still serving a config cache built
     * before it was added has no such key at all.
     *
     * v1 is retired whether or not that deployment's cache knows it, so the read is defaulted
     * rather than bare - and a bare read also passed NULL to ltrim(), which is deprecated on
     * PHP 8.1+ and left the mark at 0, answering 404 'never existed' for v1.
     */
    public function testAnAbsentEndOfLifeConfigStillRetiresV1()
    {
        $this->forgetConfig('app.api_version_eol');

        $this->assertNull(config('app.api_version_eol'), 'The key should be absent for this test');

        $response = $this->get('/api/v1/version');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(410);
        $this->assertIsTheApiErrorEnvelope($response, 'API version is End of Life and no longer supported: v1');
    }

    /** And nothing reaches ltrim() as NULL on the way there. */
    public function testAnAbsentEndOfLifeConfigRaisesNoDeprecation()
    {
        $this->forgetConfig('app.api_version_eol');

        $raised = [];

        set_error_handler(function (int $errno, string $message) use (&$raised): bool {
            $raised[] = $message;

            return TRUE;
        }, E_DEPRECATED);

        try {
            $response = $this->get('/api/v1/version');
        }
        finally {
            restore_error_handler();
        }

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $this->assertSame([], array_values(array_filter($raised, fn ($m) => str_contains($m, 'ltrim'))));
    }

    /**
     * A key that is present and empty is a deliberate 'nothing is retired', which is not the
     * same as a key that was never written - so it retires nothing rather than falling back
     * to the shipped default.
     *
     * @param mixed $eol
     */
    #[DataProvider('emptyEndOfLifeConfigDataProvider')]
    public function testAnEmptyEndOfLifeConfigRetiresNothing($eol)
    {
        config(['app.api_version_eol' => $eol]);
        config(['app.api_version_list' => ['v2', 'v3']]);

        $raised = [];

        set_error_handler(function (int $errno, string $message) use (&$raised): bool {
            $raised[] = $message;

            return TRUE;
        }, E_DEPRECATED);

        try {
            $response = $this->get('/api/v1/version');
        }
        finally {
            restore_error_handler();
        }

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(404);
        $this->assertSame([], array_values(array_filter($raised, fn ($m) => str_contains($m, 'ltrim'))));
    }

    public static function emptyEndOfLifeConfigDataProvider(): array
    {
        return [
            'null'         => [NULL],
            'empty string' => [''],
            'unparseable'  => ['none'],
        ];
    }

    /** A version that is not a whole number was never a version, whatever the EOL mark is. */
    public function testANonNumericVersionIsNeverTreatedAsEndOfLife()
    {
        config(['app.api_version_eol' => 'v10']);

        foreach(['/api/v1x/version', '/api/v2x/version', '/api/vfoo/version'] as $url) {
            $response = $this->get($url);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(404, $url);
        }
    }

    // -----------------------------------------------------------------------
    // Actions that v3 accepts on POST only
    // -----------------------------------------------------------------------

    /**
     * The POST-only gate sits after the allowed-action check, so a disabled action is 404
     * 'not found' rather than a 405 that tells the caller the feature exists.
     */
    public function testADisabledActionIs404RatherThan405()
    {
        config(['download.enable' => FALSE]);

        $response = $this->get('/api/v3/render');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(404);
        $this->assertIsTheApiErrorEnvelope($response, 'Action not found');

        $this->get('/api/v3/download')->assertStatus(404);
    }

    /**
     * From v3 on, the actions that hand back a file are POST-only; the legacy versions still
     * answer either method, so existing GET clients are not broken by the new rule.
     */
    public function testTheFileActionsArePostOnlyFromV3On()
    {
        // Both actions are behind the same gate, and the legacy-version half of this test
        // expects them to reach the engine - on an install with downloads off they are not
        // allowed actions at all and answer 404.
        if(!config('download.enable')) {
            $this->markTestSkipped('Downloads disabled');
        }

        foreach(ApiController::POST_ONLY_ACTIONS as $action) {
            $response = $this->get('/api/v3/' . $action);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(405);
            $this->assertIsTheApiErrorEnvelope($response, 'Action requires POST method');

            // The same action on v2 is not turned away for being a GET.
            $this->getJson('/api/v2/' . $action)->assertStatus(400);
            $this->getJson('/api/' . $action)->assertStatus(400);
        }
    }

    public function testThePostOnlyActionsStillAnswerAPost()
    {
        if(!config('download.enable')) {
            $this->markTestSkipped('Downloads disabled');
        }

        // An empty request is a 400 from the engine, not a 405 from the method check.
        $response = $this->postJson('/api/v3/download');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);
        $this->assertEquals(4, $response['error_level']);
    }

    // -----------------------------------------------------------------------
    // The version each response reports
    // -----------------------------------------------------------------------

    /**
     * The legacy routes keep answering as v2 even though the application default is now v3;
     * the reported version comes from the engine, not from config('app.api_version').
     */
    public function testTheLegacyRoutesStillReportV2()
    {
        foreach(['/api/version', '/api/v2/version'] as $url) {
            $response = $this->getJson($url);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(200);
            $this->assertEquals('v2', $response['results']['api_version'], $url);
        }
    }

    public function testTheStaticsActionReportsTheEnginesVersionToo()
    {
        $v2 = $this->getJson('/api/v2/statics');

        if($v2->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $v2->assertStatus(200);
        $this->assertEquals('v2', $v2['results']['api_version']);

        $v3 = $this->getJson('/api/v3/statics');
        $v3->assertStatus(200);
        $this->assertEquals('v3', $v3['results']['api_version']);
    }

    /**
     * config('app.api_version') is reported as 'api_version_current' - the version the
     * application recommends - alongside 'api_version', which is the version that actually
     * answered. The two are different numbers on a legacy request and must not be conflated:
     * writing the config value over 'api_version' made '/api/v2/statics' answer 'v3'.
     *
     * @param string $action
     */
    #[DataProvider('versionReportingActionDataProvider')]
    public function testTheCurrentVersionIsReportedSeparatelyFromTheAnsweringVersion(string $action)
    {
        $response = $this->getJson('/api/v2/' . $action);

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);

        $this->assertEquals('v2', $response['results']['api_version'], $action);
        $this->assertEquals(config('app.api_version'), $response['results']['api_version_current'], $action);
        $this->assertNotEquals(
            $response['results']['api_version'],
            $response['results']['api_version_current'],
            $action . ': the answering version was overwritten by the configured one'
        );
    }

    /** The version that answered is the engine's own on every version. */
    #[DataProvider('versionReportingActionDataProvider')]
    public function testTheCurrentVersionIsTheSameOnEveryVersionedRoute(string $action)
    {
        $v3 = $this->getJson('/api/v3/' . $action);

        if($v3->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $v3->assertStatus(200);

        $this->assertEquals('v3', $v3['results']['api_version'], $action);
        $this->assertEquals(config('app.api_version'), $v3['results']['api_version_current'], $action);
    }

    /** Both actions that report a version carry the same two fields. */
    public static function versionReportingActionDataProvider(): array
    {
        return [
            'version' => ['version'],
            'statics' => ['statics'],
        ];
    }

    /** Every version the application supports is advertised on every response. */
    public function testTheSupportedVersionListIsAdvertised()
    {
        $response = $this->getJson('/api/v2/version');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $this->assertEquals(config('app.api_version_list'), $response['results']['api_version_list']);
        $this->assertContains('v2', $response['results']['api_version_list']);
        $this->assertContains('v3', $response['results']['api_version_list']);
    }

    // -----------------------------------------------------------------------
    // The factory, against the real container
    // -----------------------------------------------------------------------

    public function testTheFactoryBuildsTheEngineForEachAdvertisedVersion()
    {
        foreach(config('app.api_version_list') as $vv) {
            $Engine = EngineFactory::getNewEngine(ltrim($vv, 'v'));

            $this->assertInstanceOf('App\Engines\EngineV' . ltrim($vv, 'v'), $Engine);
            $this->assertInstanceOf('App\Engine', $Engine);
        }
    }

    public function testTheFactoryBuildsAV2EngineWhenNoVersionIsGiven()
    {
        $this->assertInstanceOf('App\Engines\EngineV2', EngineFactory::getNewEngine());
    }

    // -----------------------------------------------------------------------
    // The factory's shared instances
    // -----------------------------------------------------------------------

    /**
     * Each version keeps its own singleton. The engines inherit Traits\Singleton through
     * App\Engine, so without a redeclared slot on each subclass the first version asked for
     * would be handed back for every other one - a v2 client silently served v3 responses,
     * or the reverse.
     */
    public function testEachVersionKeepsItsOwnSingleton()
    {
        try {
            $V2 = EngineFactory::getEngineInstance(2);
            $V3 = EngineFactory::getEngineInstance(3);

            $this->assertInstanceOf('App\Engines\EngineV2', $V2);
            $this->assertInstanceOf('App\Engines\EngineV3', $V3);
            $this->assertNotSame($V2, $V3);

            // Asking again must not disturb the other version's instance.
            $this->assertSame($V2, EngineFactory::getEngineInstance(2));
            $this->assertSame($V3, EngineFactory::getEngineInstance(3));
        }
        finally {
            EngineFactory::resetEngineInstance(2);
            EngineFactory::resetEngineInstance(3);
        }
    }

    public function testAFreshInstanceReplacesOnlyItsOwnVersion()
    {
        try {
            $V2 = EngineFactory::getEngineInstance(2);
            $V3 = EngineFactory::getEngineInstance(3);

            $V3_fresh = EngineFactory::getFreshEngineInstance(3);

            $this->assertNotSame($V3, $V3_fresh);
            $this->assertInstanceOf('App\Engines\EngineV3', $V3_fresh);
            $this->assertSame($V2, EngineFactory::getEngineInstance(2));
        }
        finally {
            EngineFactory::resetEngineInstance(2);
            EngineFactory::resetEngineInstance(3);
        }
    }

    public function testResettingOneVersionLeavesTheOtherAlone()
    {
        try {
            $V2 = EngineFactory::getEngineInstance(2);
            $V3 = EngineFactory::getEngineInstance(3);

            EngineFactory::resetEngineInstance(3);

            $this->assertSame($V2, EngineFactory::getEngineInstance(2));
            $this->assertNotSame($V3, EngineFactory::getEngineInstance(3));
        }
        finally {
            EngineFactory::resetEngineInstance(2);
            EngineFactory::resetEngineInstance(3);
        }
    }

    /**
     * The advertised list is configuration; the class behind a version is not. Advertising one
     * with no App\Engines\EngineV{n} behind it must not take down the shared setUp() - it
     * would fatal for every feature test in the suite rather than failing the one test that
     * wanted that engine, and ApiExceptionHandlingTest advertises exactly that on purpose.
     */
    public function testResettingSkipsAnAdvertisedVersionWithNoEngineClass()
    {
        $missing = 998;

        $this->assertFalse(class_exists(EngineFactory::getClassName($missing)));

        config(['app.api_version_list' => array_merge(config('app.api_version_list'), ['v' . $missing])]);

        $this->resetEngineInstances();

        // Reached at all, and the real versions were still cleared on the way past it.
        $this->assertInstanceOf('App\Engines\EngineV2', EngineFactory::getEngineInstance(2));
    }

    /**
     * Deliberately leaves its engines in their slots - no finally - so the test below can
     * assert the shared setUp() cleared them anyway.
     *
     * @return array<string, \App\Engine> The engines left behind, keyed by version
     */
    public function testAVersionedEngineIsLeftInItsSlot(): array
    {
        $left = [];

        foreach(config('app.api_version_list') as $version) {
            $number = ltrim($version, 'v');

            $left[$number] = EngineFactory::getEngineInstance($number);
        }

        $this->assertNotEmpty($left, 'No API version is advertised');

        return $left;
    }

    /**
     * Every version has a singleton slot of its own - EngineV2 and EngineV3 each redeclare
     * $instance - so resetting App\Engine alone left whichever engine a test built behind,
     * with its Bible set and its defaults, for every later test in the process to inherit.
     *
     * @param array<string, \App\Engine> $left
     */
    #[Depends('testAVersionedEngineIsLeftInItsSlot')]
    public function testTheSharedSetupClearsEveryEngineSlot(array $left): void
    {
        foreach($left as $version => $Engine) {
            $this->assertNotSame(
                $Engine,
                EngineFactory::getEngineInstance($version),
                'The API v' . $version . ' engine leaked out of the previous test'
            );
        }
    }

    /** The base engine's own singleton is separate from either versioned one. */
    public function testTheBaseEngineSingletonIsSeparate()
    {
        try {
            $Base = \App\Engine::getInstance();
            $V2   = EngineFactory::getEngineInstance(2);

            $this->assertNotSame($Base, $V2);
            $this->assertInstanceOf('App\Engine', $Base);
            $this->assertNotInstanceOf('App\Engines\EngineV2', $Base);
        }
        finally {
            \App\Engine::resetInstance();
            EngineFactory::resetEngineInstance(2);
        }
    }
}
