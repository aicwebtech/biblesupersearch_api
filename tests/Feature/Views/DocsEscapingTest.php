<?php

namespace Tests\Feature\Views;

use Tests\TestCase;

/**
 * The docs views are plain .php, not Blade, so nothing is escaped automatically.
 * app.name and app.client_url are both settable from the admin config form and
 * rendered on this public page.
 */
class DocsEscapingTest extends TestCase
{
    /**
     * A script-capable client_url must not reach the href at all.
     */
    public function testJavascriptClientUrlIsNotRendered(): void
    {
        config(['app.client_url' => 'javascript:alert(1)']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('javascript:alert(1)', false);
    }

    public function testDataUriClientUrlIsNotRendered(): void
    {
        config(['app.client_url' => 'data:text/html;base64,PHNjcmlwdD4=']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('data:text/html', false);
    }

    /**
     * A legitimate URL must still render, so the guard is not simply dropping
     * the link for everyone.
     */
    public function testHttpsClientUrlIsStillRendered(): void
    {
        config(['app.client_url' => 'https://example.org/client']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('https://example.org/client', false);
    }

    /**
     * app.name is echoed in several places including the <title>.
     */
    public function testApplicationNameIsEscaped(): void
    {
        config(['app.name' => '<script>alert(1)</script>']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    /**
     * A quote in app.name must not break the JSON code sample in the docs.
     */
    public function testApplicationNameWithQuoteDoesNotBreakJsonSample(): void
    {
        config(['app.name' => 'Say "hello"']);

        $response = $this->get('/');

        $response->assertStatus(200);

        // Escaped for HTML in the prose/title...
        $response->assertSee('Say &quot;hello&quot;', false);

        // ...and JSON-escaped inside the code sample, so the quote closes
        // neither the HTML attribute nor the sample's JSON string.
        $response->assertSee('Say \\&quot;hello\\&quot;', false);
        $response->assertDontSee('"name": "Say "hello""', false);
    }
}
