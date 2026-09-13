<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Factories\EngineFactory;

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
        $response->assertSee('API version not found: v9');

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
        $response->assertSee('Action not found');
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
     * The versioned route is '/api/v{version}', so it claims every path whose first segment
     * begins with 'v' - '/api/version' included, read as version 'ersion'. The controller's
     * disambiguation list rescues that one and serves it as the v2 'version' action; without
     * it the legacy '/api/version' endpoint would answer 'API version not found: version'.
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
     * 'version' is the only action whose name begins with 'v', which is why a one-entry
     * disambiguation list is enough. The action names are taken from the engine's own
     * action*() methods rather than restated here, so a new action named 'v...' fails this
     * the day it is added - otherwise the versioned route swallows it and the generic route
     * never sees it.
     */
    public function testNoOtherActionNameCollidesWithTheVersionPrefix()
    {
        foreach((new \ReflectionClass(\App\Engine::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $Method) {
            if(!str_starts_with($Method->getName(), 'action')) {
                continue;
            }

            $action = \Illuminate\Support\Str::snake(substr($Method->getName(), strlen('action')));

            if($action == 'version') {
                continue;
            }

            $this->assertStringStartsNotWith('v', $action, $action . ' collides with the versioned route prefix - add it to the disambiguation list in ApiController::versionedAction()');
        }
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
        $response->assertSee('API version is End of Life and no longer supported: v1');

        $this->post('/api/v1/query')->assertStatus(410);
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
        $response->assertSee('API version not found: v0');
    }

    // -----------------------------------------------------------------------
    // Actions that v3 accepts on POST only
    // -----------------------------------------------------------------------

    /**
     * From v3 on, the actions that hand back a file are POST-only; the legacy versions still
     * answer either method, so existing GET clients are not broken by the new rule.
     */
    public function testTheFileActionsArePostOnlyFromV3On()
    {
        foreach(['render', 'download'] as $action) {
            $response = $this->get('/api/v3/' . $action);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(405);
            $response->assertSee('Action requires POST method');

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
