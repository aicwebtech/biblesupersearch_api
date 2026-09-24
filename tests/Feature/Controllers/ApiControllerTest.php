<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequests;

/* Always test with public access enabled */
/* Difficult if not impossible to get these tests to work with public access disabled, so not doing that now ... */
class ApiControllerTest extends TestCase
{
    protected $config_cache;
    protected $config_value = 1;
    protected $config_changed = false;

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

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
    }

    /**
     * Tests of the 'statics' action
     *
     * @return void
     */
    public function testActionStatics()
    {
        // GET
        $response = $this->withoutMiddleware(ThrottleRequests::class)->getJson('/api/statics?language=es');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('Romanos', $response['results']['books'][44]['name']);
        $this->assertEquals('KJV', $response['results']['bibles']['kjv']['shortname']);
        $this->assertEquals('entire', $response['results']['bibles']['kjv']['book_list']);
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);

        // POST
        $response = $this->postJson('/api/statics', ['language' => 'es']);
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('Romanos', $response['results']['books'][44]['name']);
        $this->assertEquals('KJV', $response['results']['bibles']['kjv']['shortname']);
        $this->assertEquals('entire', $response['results']['bibles']['kjv']['book_list']);
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);

        // Versioned
        // GET
        $response = $this->getJson('/api/v2/statics?language=es');

        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('Romanos', $response['results']['books'][44]['name']);
        $this->assertEquals('KJV', $response['results']['bibles']['kjv']['shortname']);
        $this->assertEquals('entire', $response['results']['bibles']['kjv']['book_list']);
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);
    }       

    /**
     * Tests of the 'bibles' action
     * Note: the UI doesn't actually use this action
     *
     * @return void
     */
    public function testActionBibles()
    {
        // GET
        $response = $this->getJson('/api/bibles?language=es');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('KJV', $response['results']['kjv']['shortname']);
        $this->assertEquals('entire', $response['results']['kjv']['book_list']);

        // POST
        $response = $this->postJson('/api/bibles', ['language' => 'es']);

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('KJV', $response['results']['kjv']['shortname']);
        $this->assertEquals('entire', $response['results']['kjv']['book_list']);
    }   

    /**
     * Tests of the 'statics' action
     *
     * @return void
     */
    public function testActionBooks()
    {
        // GET
        $response = $this->getJson('/api/books?language=es');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('Romanos', $response['results'][44]['name']);

        // POST
        $response = $this->postJson('/api/books', ['language' => 'es']);
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('Romanos', $response['results'][44]['name']);
    }    

    /**
     * Tests of the default ('query') action
     *
     * @return void
     */
    public function testActionDefaultQuery()
    {
        // GET - empty request
        $response = $this->getJson('/api');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        $this->assertContains(__('errors.no_query'), $response['errors']);
        
        // POST - empty request
        $response = $this->postJson('/api');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);

        // GET
        $response = $this->getJson('/api?request=faith&bible=kjv');
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);

        // POST
        $response = $this->postJson('/api', ['request' => 'faith', 'bible' => 'kjv']);
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);
    }    

    /**
     * Tests of the default ('query') action
     *
     * @return void
     */
    public function testActionDefaultQueryVersioned()
    {
        // GET - empty request
        $response = $this->getJson('/api/v2');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        $this->assertContains(__('errors.no_query'), $response['errors']);
        
        // POST - empty request
        $response = $this->postJson('/api/v2');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);

        // GET
        $response = $this->getJson('/api/v2?request=faith&bible=kjv');
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);

        // POST
        $response = $this->postJson('/api/v2', ['request' => 'faith', 'bible' => 'kjv']);
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);
    }  

    /**
     * Tests of the 'query' action
     *
     * @return void
     */
    public function testActionQuery()
    {
        // GET - empty request
        $response = $this->getJson('/api/query');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api/query');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);

        // GET
        $response = $this->getJson('/api/query?request=faith&bible=kjv');
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);

        // POST
        $response = $this->postJson('/api/query', ['request' => 'faith', 'bible' => 'kjv']);
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);
    }    

    /**
     * A highlight_tag the version does not accept is reported over HTTP.
     *
     * The results are still there and still highlighted - with the default tag - but the
     * response now carries the substitution in 'errors', where a client can see it. The 400
     * is what this API answers with whenever 'errors' is non-empty, non-fatal included.
     *
     * @return void
     */
    public function testARejectedHighlightTagIsReportedOverHttp()
    {
        $response = $this->getJson('/api/query?request=faith&bible=kjv&highlight=1&highlight_tag=my-tag');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);
        $this->assertEquals(3, $response['error_level']);
        $this->assertCount(1, $response['errors']);
        $this->assertStringContainsString("'my-tag'", $response['errors'][0]);
        $this->assertEquals(338, $response['paging']['total']);

        // An accepted tag is unchanged: 200, no errors.
        $response = $this->getJson('/api/query?request=faith&bible=kjv&highlight=1&highlight_tag=em');
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
    }

    /**
     * v3 narrows the whitelist to the Markdown markers, so an element name a v2 client has
     * always sent is refused there - and says so, rather than quietly answering in bold.
     *
     * @return void
     */
    public function testAnElementNameIsReportedOnV3AndNotOnV2()
    {
        $v2 = $this->getJson('/api/v2/query?request=faith&bible=kjv&highlight=1&highlight_tag=em');

        if($v2->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $v2->assertStatus(200);
        $this->assertEquals(0, $v2['error_level']);

        $v3 = $this->getJson('/api/v3/query?request=faith&bible=kjv&highlight=1&highlight_tag=em');
        $v3->assertStatus(400);
        $this->assertEquals(3, $v3['error_level']);
        $this->assertStringContainsString("'em'", $v3['errors'][0]);
    }

    /**
     * Tests of the 'version' action
     *
     * @return void
     */
    public function testVersionAction()
    {
        // GET
        $response = $this->getJson('/api/version');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);
        
        // POST
        $response = $this->postJson('/api/version');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);
    }    

    /**
     * Tests of the 'strongs' action
     *
     * @return void
     */
    public function testStrongsAction()
    {
        // GET - empty request
        $response = $this->getJson('/api/strongs');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api/strongs');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);

        // GET
        $response = $this->getJson('/api/strongs?strongs=H1234');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(1234, $response['results'][0]['id']);
        
        // POST
        $response = $this->postJson('/api/strongs', ['strongs' => 'H1234']);
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(1234, $response['results'][0]['id']);
    }    

    /**
     * Tests of the 'access' action
     *
     * The caller's own quota state, split out of 'statics' so a client has somewhere to read
     * it from that is not the large, otherwise-cacheable boot payload.
     *
     * @return void
     */
    public function testActionAccess()
    {
        $uris = ['/api/access', '/api/v2/access', '/api/v3/access'];

        foreach($uris as $uri) {
            // GET
            $response = $this->withoutMiddleware(ThrottleRequests::class)->getJson($uri);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(200);
            $this->assertEquals(0, $response['error_level'], $uri);
            $this->assertIsBool($response['results']['allowed'], $uri);
            $this->assertIsInt($response['results']['limit'], $uri);
            $this->assertIsBool($response['results']['limit_reached'], $uri);
            $this->assertIsInt($response['results']['hits'], $uri);

            // POST
            $response = $this->withoutMiddleware(ThrottleRequests::class)->postJson($uri);

            $response->assertStatus(200);
            $this->assertEquals(0, $response['error_level'], $uri);
            $this->assertIsBool($response['results']['allowed'], $uri);
        }
    }

    /**
     * The action answers with exactly the object 'statics' carries as its 'access' key, so a
     * client migrating off 'statics' swaps one for the other without reshaping anything.
     *
     * @return void
     */
    public function testTheAccessActionMatchesTheStaticsAccessBlock()
    {
        $access  = $this->withoutMiddleware(ThrottleRequests::class)->getJson('/api/access');
        $statics = $this->withoutMiddleware(ThrottleRequests::class)->getJson('/api/statics');

        if($access->status() == 429 || $statics->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $access->assertStatus(200);
        $statics->assertStatus(200);

        $this->assertSame(array_keys($statics['results']['access']), array_keys($access['results']));
    }

    /**
     * 'statics' keeps carrying the access block for now.
     *
     * The action above is the replacement, but removing the block would break every client at
     * once, so both are published until the migration is done. This guards against the removal
     * happening by accident rather than deliberately.
     *
     * @return void
     */
    public function testStaticsStillCarriesTheAccessBlock()
    {
        $response = $this->withoutMiddleware(ThrottleRequests::class)->getJson('/api/statics');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);

        foreach(['allowed', 'limit', 'limit_reached', 'hits'] as $key) {
            $this->assertArrayHasKey($key, $response['results']['access'], $key);
        }
    }

    /**
     * The response is the caller's own state, so it must never be stored by any cache - it is
     * deliberately absent from bss.cache_headers.actions, and unlisted actions get no
     * Cache-Control at all.
     *
     * @return void
     */
    public function testTheAccessActionIsNotCached()
    {
        foreach(['/api/access', '/api/v2/access', '/api/v3/access'] as $uri) {
            $response = $this->withoutMiddleware(ThrottleRequests::class)->getJson($uri);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(200);

            $cacheControl = (string) $response->headers->get('Cache-Control');

            $this->assertStringNotContainsString('public', $cacheControl, $uri);
            $this->assertStringNotContainsString('private, max-age', $cacheControl, $uri);
        }
    }

    /**
     * Asking how much quota is left must not spend any of it.
     *
     * Asserted on the configuration rather than by calling the endpoint twice and comparing
     * 'hits': the daily counter is a shared row and the suite runs under paratest, so a
     * behavioral assertion here would be flaky.
     *
     * @return void
     */
    public function testTheAccessActionIsFree()
    {
        $this->assertContains('access', config('bss.free_actions'));
    }

    /**
     * Cacheable read endpoints (GET) should send public Cache-Control + ETag
     * and must NOT set session/CSRF cookies (BSS-272).
     *
     * @return void
     */
    public function testCacheHeadersOnReadEndpoints()
    {
        $cases = [
            '/api/books?language=es'            => 86400,
            '/api/query?request=faith&bible=kjv' => 3600,
        ];

        foreach($cases as $uri => $maxAge) {
            $response = $this->getJson($uri);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(200);

            $cacheControl = (string) $response->headers->get('Cache-Control');
            $this->assertNotSame('', $cacheControl, "Cache-Control missing for {$uri}");
            $this->assertStringContainsString('public', $cacheControl, "Cache-Control public missing for {$uri}");
            $this->assertStringContainsString('max-age=' . $maxAge, $cacheControl, "max-age missing for {$uri}");
            $this->assertNotEmpty($response->headers->get('ETag'), "ETag missing for {$uri}");
            $this->assertEmpty($response->headers->get('Set-Cookie'), "Set-Cookie present for {$uri}");
        }
    }

    /**
     * The versioned routes are cached on the same terms as the unversioned ones.
     *
     * SetCacheHeaders parsed the path itself and recognized only the literal 'v2', so a
     * '/api/v3/bibles' request resolved to the action 'v3', matched nothing in
     * bss.cache_headers.actions and went out with no Cache-Control at all. Both middlewares
     * read the action through Helpers::resolveApiAction() now.
     *
     * @return void
     */
    public function testCacheHeadersOnVersionedReadEndpoints()
    {
        $cases = [
            '/api/v2/books?language=es'   => 86400,
            '/api/v3/books?language=es'   => 86400,
            '/api/v2/bibles?language=es'  => 86400,
            '/api/v3/bibles?language=es'  => 86400,
        ];

        foreach($cases as $uri => $maxAge) {
            $response = $this->getJson($uri);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(200);

            $cacheControl = (string) $response->headers->get('Cache-Control');

            $this->assertStringContainsString('public', $cacheControl, "Cache-Control public missing for {$uri}");
            $this->assertStringContainsString('max-age=' . $maxAge, $cacheControl, "max-age missing for {$uri}");
            $this->assertNotEmpty($response->headers->get('ETag'), "ETag missing for {$uri}");
            $this->assertEmpty($response->headers->get('Set-Cookie'), "Set-Cookie present for {$uri}");
        }
    }

    /**
     * 'statics' is cached, but only by the caller's own browser.
     *
     * Its response embeds the caller's own access/quota state (Engine::actionStatics()),
     * which ApiAccessManager buckets by API key or, keyless, by IP and Origin/Referer. The
     * key is a query parameter and so part of a cache key; the IP is not, and no header
     * carries it. A shared cache keying on the URL would therefore store one caller's quota
     * state and serve it to the next caller for the whole max-age.
     *
     * @return void
     */
    public function testStaticsIsPrivatelyCached()
    {
        $uris = [
            '/api/statics?language=es',
            '/api/v2/statics?language=es',
            '/api/v3/statics?language=es',
        ];

        foreach($uris as $uri) {
            $response = $this->getJson($uri);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(200);

            $cacheControl = (string) $response->headers->get('Cache-Control');

            $this->assertStringContainsString('private', $cacheControl, "Cache-Control private missing for {$uri}");
            $this->assertStringNotContainsString('public', $cacheControl, "Cache-Control public present for {$uri}");
            $this->assertStringContainsString('max-age=3600', $cacheControl, "max-age missing for {$uri}");
            $this->assertNotEmpty($response->headers->get('ETag'), "ETag missing for {$uri}");
            $this->assertEmpty($response->headers->get('Set-Cookie'), "Set-Cookie present for {$uri}");
        }
    }

    /**
     * A per-action visibility may narrow the configured default, never widen it.
     *
     * API_CACHE_HEADERS_VISIBILITY=private is an operator kill switch: every cached action
     * must honor it, whether it carries a visibility of its own or inherits the default.
     *
     * @return void
     */
    public function testAPerActionVisibilityCannotWidenTheGlobalDefault()
    {
        config(['bss.cache_headers.visibility' => 'private']);
        config(['bss.cache_headers.actions.books' => ['max_age' => 86400, 'visibility' => 'public']]);

        foreach(['/api/books?language=es', '/api/statics?language=es'] as $uri) {
            $response = $this->getJson($uri);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(200);

            $cacheControl = (string) $response->headers->get('Cache-Control');

            $this->assertStringContainsString('private', $cacheControl, "Cache-Control private missing for {$uri}");
            $this->assertStringNotContainsString('public', $cacheControl, "Cache-Control public present for {$uri}");
        }
    }

    /**
     * A versioned request with no action is the 'query' action, and is cached as one - the
     * route defaults it, and the resolver has to default it the same way.
     *
     * @return void
     */
    public function testCacheHeadersOnAVersionedRouteWithNoAction()
    {
        $response = $this->getJson('/api/v3?request=faith&bible=kjv');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    /**
     * An action with no configured max-age is not cached, on a versioned route either - the
     * version segment must not be mistaken for the action and vice versa.
     *
     * @return void
     */
    public function testAnUncachedActionIsNotCachedOnAVersionedRoute()
    {
        foreach(['/api/version', '/api/v2/version', '/api/v3/version'] as $uri) {
            $response = $this->getJson($uri);

            if($response->status() == 429) {
                $this->markTestSkipped('429 Skipping due to rate limiting');
            }

            $response->assertStatus(200);
            $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'), $uri);
        }
    }

    /**
     * A matching If-None-Match should yield a 304 Not Modified (BSS-272).
     *
     * @return void
     */
    public function test304OnMatchingEtag()
    {
        $response = $this->getJson('/api/books?language=es');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $cached = $this->getJson('/api/books?language=es', ['If-None-Match' => $etag]);
        $cached->assertStatus(304);
        $this->assertEmpty($cached->getContent());
    }

    /**
     * POST (non-idempotent) requests must not be marked publicly cacheable (BSS-272).
     *
     * @return void
     */
    public function testNoCacheHeadersOnPost()
    {
        $response = $this->postJson('/api/books', ['language' => 'es']);

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * Error (non-200) responses must not be marked publicly cacheable (BSS-272).
     *
     * @return void
     */
    public function testErrorResponsesNotCached()
    {
        $response = $this->getJson('/api/query');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * JSONP responses (a 'callback' is present) must not be marked publicly
     * cacheable, even though they are GET 200 responses (BSS-272).
     *
     * @return void
     */
    public function testJsonpResponsesNotCached()
    {
        $response = $this->getJson('/api/books?language=es&callback=mycb');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * Pretty-printed error views are HTML 200 responses and must not be
     * marked publicly cacheable (BSS-272).
     *
     * @return void
     */
    public function testPrettyPrintedErrorsNotCached()
    {
        $response = $this->get('/api/query?pretty_print=1');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * Tests of the 'download' action
     *
     * @return void
     */
    public function testDownloadAction()
    {
        if(!config('download.enable')) {
            $this->markTestSkipped('Downloads disabled');
        }

        // GET - empty request
        $response = $this->getJson('/api/download');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api/download');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);        

        // GET - empty request, pretty_print errors
        $response = $this->get('/api/download?pretty_print=true');
        $response->assertStatus(200); // should this be so?        
        
        // POST - empty request, pretty_print errors
        $response = $this->postJson('/api/download', ['pretty_print' => TRUE]);
        $response->assertStatus(200);        

        // Attempting to test actual file download results in "headers already sent" errors, unable to test here!

        // GET
        // $response = $this->getJson('/api/download?bible=kjv&format=csv');
        // $response->assertStatus(200);        
        
        // POST
        // $response = $this->postJson('/api/download', ['bible' => 'kjv', 'format' => 'csv']);
        // $response->assertStatus(200);        
    }

    /**
     * Tests of the 'render_needed' action
     *
     * @return void
     */
    public function testRenderNeededAction()
    {
        if(!config('download.enable')) {
            $this->markTestSkipped('Downloads disabled');
        }

        // Render a file.  
        $Renderer = new \App\Renderers\Csv('kjv');
        $Renderer->renderIfNeeded();

        // GET - empty request
        $response = $this->getJson('/api/render_needed');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api/render_needed');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);        

        // GET
        $response = $this->getJson('/api/render_needed?bible=kjv&format=csv');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        
        // POST
        $response = $this->postJson('/api/render_needed', ['bible' => 'kjv', 'format' => 'csv']);

        // attempt to trap intermittint error
        if($response->getStatusCode() != 200) {
            $this->markTestSkipped('Intermittent error #1, status code: ' . $response->getStatusCode());
            echo 'Intermittent error #1, status code: ' . $response->getStatusCode() . PHP_EOL;
            var_dump($response->getStatusCode());
            var_dump($response['error_level']);
            var_dump($response['errors']);
            var_dump($response['results']);
            $this->assertEquals(1, $response['error_level']);
            $this->assertEquals(TRUE, $response['results']['render_needed']);
        }
        else {
            $response->assertStatus(200);        
        }

        $this->assertEquals(0, $response['error_level']);
    }    

    /*
     * This tests the render_needed flag on the render action
     */
    public function testRenderNeededFlag() 
    {
        if(!config('download.enable')) {
            $this->markTestSkipped('Downloads disabled');
        }

        // Test NOT needing render

        // Render a file.  
        $Renderer = new \App\Renderers\MachineReadableText('kjv');
        $Renderer->renderIfNeeded();
        $RR = $Renderer->_getRenderingRecord();
        $file_path = $RR->getRenderedFilePath();

        $this->assertFalse($Renderer->hasErrors());
        $this->assertFileExists($file_path);

        $response = $this->postJson('/api/render_needed', ['bible' => 'kjv', 'format' => 'mr_text']);

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);      

        $this->assertEquals(0, $response['error_level']);
        $this->assertFalse($response['results']['render_needed']);
        $this->assertIsArray($response['results']['bibles_needing_render']);
        $this->assertEmpty($response['results']['bibles_needing_render']);

        // Test needing render

        // Delete the rendered file
        $Renderer->deleteRenderFile();
        $this->assertFileDoesNotExist($file_path);

        $response = $this->postJson('/api/render_needed', ['bible' => 'kjv', 'format' => 'mr_text']);

        // Yes, this is returned as an 'error'
        $response->assertStatus(400);         
        $this->assertEquals(1, $response['error_level']);
        $this->assertTrue($response['results']['render_needed']);
        $this->assertIsArray($response['results']['bibles_needing_render']);
        $this->assertContains('kjv', $response['results']['bibles_needing_render']);
    }

    /**
     * Tests of the 'render' action
     *
     * @return void
     */
    public function testRenderAction()
    {
        if(!config('download.enable')) {
            $this->markTestSkipped('Downloads disabled');
        }

        // GET - empty request
        $response = $this->getJson('/api/render');

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api/render');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);        

        // GET
        $response = $this->getJson('/api/render?bible=kjv&format=csv');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        
        // POST
        $response = $this->postJson('/api/render', ['bible' => 'kjv', 'format' => 'csv']);
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
    }

}
