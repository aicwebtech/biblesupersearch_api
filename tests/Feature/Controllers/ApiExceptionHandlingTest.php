<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * BSS-290: ApiController::genericAction() catches \Throwable around the engine call.
 *
 * The catch used to name an unqualified Exception, which PHP resolves inside the controller's
 * own namespace - App\Http\Controllers\Exception, a class that does not exist - so nothing was
 * ever caught and a production deployment leaked a stack trace instead of the 500 the block
 * was written to send. Naming \Exception fixed that but still missed \Error, which is what a
 * TypeError from a sanitizer or a missing engine class actually raises. Both arms of the block
 * are exercised here, for an Exception and for an Error.
 *
 * The engine that throws is a test double registered under a synthetic API version; it is
 * declared at the foot of this file, in the namespace EngineFactory looks in.
 */
class ApiExceptionHandlingTest extends TestCase
{
    /** The synthetic API version App\Engines\EngineV999 (below) answers for. */
    private const THROWING_VERSION = 999;

    /** The message that double throws. */
    public const BOOM = 'EngineV999 test double detonated';

    /** The message the double raises as an \Error rather than an \Exception. */
    public const KABOOM = 'EngineV999 test double raised an Error';

    /** An API version that is advertised but has no App\Engines\EngineV{n} class behind it. */
    private const MISSING_VERSION = 998;

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['bss.public_access' => 1]);

        // The controller checks the advertised list before it asks the factory for an engine,
        // so the double has to be advertised for the request to reach it at all.
        config(['app.api_version_list' => array_merge(
            config('app.api_version_list'),
            ['v' . self::THROWING_VERSION, 'v' . self::MISSING_VERSION]
        )]);
    }

    private function url(string $action = 'version'): string
    {
        return '/api/v' . self::THROWING_VERSION . '/' . $action;
    }

    /**
     * In production the message is all the client gets, and it arrives as a 500 rather than as
     * whatever the framework's debug handler would have rendered.
     */
    public function testAnEngineExceptionBecomesA500InProduction(): void
    {
        config(['app.env' => 'production']);

        $response = $this->get($this->url());

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(500);

        // The controller's own response, not the framework's: the body is exactly the message
        // and it carries the API's headers. An uncaught exception renders neither.
        $this->assertSame(self::BOOM, $response->getContent());
        $this->assertStringStartsWith('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testAnEngineExceptionBecomesA500InProductionOnPostToo(): void
    {
        config(['app.env' => 'production']);

        $response = $this->post($this->url());

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(500);
        $this->assertSame(self::BOOM, $response->getContent());
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Outside production the exception is deliberately rethrown so the developer sees the
     * trace. It must reach the handler as itself, not as something the catch swallowed.
     */
    public function testAnEngineExceptionIsRethrownOutsideProduction(): void
    {
        config(['app.env' => 'testing']);

        $this->withoutExceptionHandling();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(self::BOOM);

        $this->get($this->url());
    }

    /**
     * The 500 arm belongs to the engine call only - an action name the controller does not
     * recognise is still the plain 404 it always was, even in production.
     */
    public function testAnUnknownActionIsStill404InProduction(): void
    {
        config(['app.env' => 'production']);

        $response = $this->get($this->url('no_such_action'));

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(404);
        $response->assertSee('Action not found');
    }

    // -----------------------------------------------------------------------
    // \Error, which is not an \Exception
    // -----------------------------------------------------------------------

    /**
     * A TypeError is an \Error, so a catch naming \Exception lets it past and the client gets
     * the framework's HTML error page instead of the JSON 500 the block exists to produce.
     * Helpers::sanitizeHtml() raised exactly this for every nullable field it guards.
     */
    public function testAnEngineErrorBecomesA500InProduction(): void
    {
        config(['app.env' => 'production']);

        $response = $this->get($this->url('books'));

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(500);
        $this->assertSame(self::KABOOM, $response->getContent());
        $this->assertStringStartsWith('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testAnEngineErrorIsRethrownOutsideProduction(): void
    {
        config(['app.env' => 'testing']);

        $this->withoutExceptionHandling();

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage(self::KABOOM);

        $this->get($this->url('books'));
    }

    /**
     * EngineFactory deliberately does not validate the version - it concatenates the class
     * name and lets `new` fail - so a version listed in app.api_version_list without a class
     * behind it clears the controller's 404 check and then raises an \Error. That is a
     * misconfiguration, but it must still leave the API answering JSON.
     */
    public function testAnAdvertisedVersionWithNoEngineClassBecomesA500InProduction(): void
    {
        config(['app.env' => 'production']);

        $this->assertFalse(class_exists('App\\Engines\\EngineV' . self::MISSING_VERSION));

        $response = $this->get('/api/v' . self::MISSING_VERSION . '/version');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(500);
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /** A healthy action on the same engine still answers normally. */
    public function testTheDoubleOnlyThrowsForTheActionUnderTest(): void
    {
        config(['app.env' => 'production']);

        $response = $this->getJson($this->url('requirements'));

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
    }
}

namespace App\Engines;

/**
 * Stands in for an engine whose action throws, so ApiController's catch block has something to
 * catch. Declared here rather than in app/: EngineFactory turns a version straight into a class
 * name in this namespace, and version 999 is never advertised outside the test above.
 */
class EngineV999 extends \App\Engine
{
    /** Own singleton slot - see the note on EngineV2. */
    protected static $instance = NULL;

    protected static $api_version = 999;

    public function actionVersion($input)
    {
        throw new \RuntimeException(\Tests\Feature\Controllers\ApiExceptionHandlingTest::BOOM);
    }

    /** Raises an \Error rather than an \Exception - the case a catch on \Exception misses. */
    public function actionBooks($input)
    {
        throw new \TypeError(\Tests\Feature\Controllers\ApiExceptionHandlingTest::KABOOM);
    }
}
