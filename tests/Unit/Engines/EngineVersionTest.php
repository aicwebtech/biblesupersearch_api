<?php

namespace Tests\Unit\Engines;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Engine;
use App\Engines\EngineV2;
use App\Engines\EngineV3;

/**
 * BSS-290: the versioned engines. EngineV2 is the legacy behaviour (HTML in, whitelisted HTML
 * out) and EngineV3 answers the same requests with Markdown, the two differing only in the
 * _processHtml() hook every HTML-bearing field ends up in - either directly, when the value was
 * already sanitized by its model accessor, or through _sanitizeHtml(), which whitelists first.
 *
 * The hook and the fields that call it are pure, so they are exercised here on engines built
 * without their database-dependent constructor. The wiring of a version onto a route lives in
 * tests/Feature/Controllers/ApiVersionRoutingTest.php.
 */
class EngineVersionTest extends TestCase
{
    /** An engine of $class with the (database-dependent) constructor skipped. */
    private function engine(string $class): Engine
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    /**
     * Calls one of the engine's protected methods. No setAccessible() call: reflection has
     * ignored visibility since PHP 8.1 and the method is deprecated in 8.5.
     */
    private function call(Engine $Engine, string $method, array $args = [])
    {
        $Method = new \ReflectionMethod($Engine, $method);

        return $Method->invokeArgs($Engine, $args);
    }

    /**
     * Reads the engine's protected static $api_version. No setAccessible() call: reflection has
     * ignored visibility since PHP 8.1 and the method is deprecated in 8.5.
     */
    private function apiVersion(string $class)
    {
        $Property = new \ReflectionProperty($class, 'api_version');

        return $Property->getValue();
    }

    /** One Bible's worth of results, in the shape _processMarkup() expects. */
    private function results(string $text): array
    {
        $Verse = new \stdClass();
        $Verse->text = $text;

        return ['kjv' => [$Verse]];
    }

    // -----------------------------------------------------------------------
    // Version identity
    // -----------------------------------------------------------------------

    /**
     * The version reported in the 'statics' and 'version' responses is the engine's own, not
     * config('app.api_version') - a v2 request must keep answering 'v2' after the application
     * default moves on.
     */
    public function testEachEngineCarriesItsOwnApiVersion(): void
    {
        $this->assertSame(2, $this->apiVersion(EngineV2::class));
        $this->assertSame(3, $this->apiVersion(EngineV3::class));
        $this->assertSame(2, $this->apiVersion(Engine::class));
    }

    public function testTheVersionedEnginesExtendTheBaseEngine(): void
    {
        $this->assertInstanceOf(Engine::class, $this->engine(EngineV2::class));
        $this->assertInstanceOf(Engine::class, $this->engine(EngineV3::class));
    }

    /**
     * Traits\Singleton declares $instance on the trait, so App\Engine owns one slot for the
     * whole hierarchy unless each engine redeclares it. Without the redeclaration
     * EngineFactory::getEngineInstance() hands back whichever version was asked for first,
     * regardless of the version requested - see the feature test for that behaviour.
     */
    public function testEachEngineDeclaresItsOwnSingletonSlot(): void
    {
        foreach([EngineV2::class, EngineV3::class] as $class) {
            $Property = new \ReflectionProperty($class, 'instance');

            $this->assertSame($class, $Property->getDeclaringClass()->getName(), $class . ' shares its singleton slot');
        }
    }

    // -----------------------------------------------------------------------
    // _sanitizeHtml / _processHtml - the hooks that differ between the versions
    // -----------------------------------------------------------------------

    public function testTheV2EngineReturnsWhitelistedHtml(): void
    {
        $Engine = $this->engine(EngineV2::class);

        $this->assertSame('<b>bold</b>', $this->call($Engine, '_sanitizeHtml', ['<b>bold</b>']));
        $this->assertSame('', $this->call($Engine, '_sanitizeHtml', ['<script>alert(1)</script>']));
    }

    public function testTheV3EngineReturnsMarkdown(): void
    {
        $Engine = $this->engine(EngineV3::class);

        $this->assertSame('**bold**', $this->call($Engine, '_sanitizeHtml', ['<b>bold</b>']));
        $this->assertSame('*ital*', $this->call($Engine, '_sanitizeHtml', ['<i>ital</i>']));
        $this->assertSame('', $this->call($Engine, '_sanitizeHtml', ['<script>alert(1)</script>']));
    }

    /**
     * The sanitizing half of the hook is shared: _sanitizeHtml() whitelists the HTML and then
     * defers to _processHtml(), and only that second half is overridden. EngineV2 is a stub -
     * the v2 behaviour is the base class's - so both methods must still be declared on
     * App\Engine for it, and EngineV3 must override _processHtml() and nothing else.
     *
     * An EngineV3 that overrode _sanitizeHtml() instead would skip the whitelist, and the
     * fields that are sanitized by their model accessor and only processed here (see
     * _formatStrongs()) would come back as HTML rather than Markdown.
     */
    public function testOnlyTheV3EngineOverridesTheProcessHook(): void
    {
        foreach(['_sanitizeHtml', '_processHtml'] as $method) {
            $this->assertSame(Engine::class, (new \ReflectionMethod(EngineV2::class, $method))->getDeclaringClass()->getName(), $method);
        }

        $this->assertSame(Engine::class, (new \ReflectionMethod(EngineV3::class, '_sanitizeHtml'))->getDeclaringClass()->getName());
        $this->assertSame(EngineV3::class, (new \ReflectionMethod(EngineV3::class, '_processHtml'))->getDeclaringClass()->getName());
    }

    /**
     * _processHtml() is for values that arrive already sanitized, so the base class hands them
     * straight back and only v3 rewrites them.
     */
    public function testTheProcessHookPassesSanitizedHtmlThroughOnV2AndConvertsItOnV3(): void
    {
        $this->assertSame('<b>bold</b>', $this->call($this->engine(EngineV2::class), '_processHtml', ['<b>bold</b>']));
        $this->assertSame('**bold**', $this->call($this->engine(EngineV3::class), '_processHtml', ['<b>bold</b>']));
    }

    // -----------------------------------------------------------------------
    // _processMarkup - verse text
    // -----------------------------------------------------------------------

    public function testProcessMarkupStripsBibleMarkupAndSanitizesVerseText(): void
    {
        $Engine  = $this->engine(EngineV2::class);
        $results = $this->results('And God <b>said</b>, ‹Let› [there] be {H1961} light<script>alert(1)</script>');

        $processed = $this->call($Engine, '_processMarkup', [$results, 'none']);

        $this->assertSame('And God <b>said</b>, Let there be  light', $processed['kjv'][0]->text);
    }

    /**
     * 'raw' keeps the Bible's own markup - the Strong's braces and the quotation carets - but
     * BSS-290 made it pass through the HTML sanitizer all the same, so raw is no longer a way
     * to get unfiltered HTML out of the API.
     */
    public function testProcessMarkupRawKeepsBibleMarkupButStillSanitizesHtml(): void
    {
        $Engine  = $this->engine(EngineV2::class);
        $results = $this->results('And God <b>said</b>, ‹Let› [there] be {H1961} light<script>alert(1)</script>');

        $processed = $this->call($Engine, '_processMarkup', [$results, 'raw']);

        $this->assertStringContainsString('‹Let›', $processed['kjv'][0]->text);
        $this->assertStringContainsString('[there]', $processed['kjv'][0]->text);
        $this->assertStringContainsString('{H1961}', $processed['kjv'][0]->text);
        $this->assertStringContainsString('<b>said</b>', $processed['kjv'][0]->text);
        $this->assertStringNotContainsString('<script>', $processed['kjv'][0]->text);
        $this->assertStringNotContainsString('alert(1)', $processed['kjv'][0]->text);
    }

    public function testProcessMarkupOnTheV3EngineReturnsMarkdownVerseText(): void
    {
        $Engine  = $this->engine(EngineV3::class);
        $results = $this->results('And God said, <i>Let</i> there be <b>light</b>');

        $processed = $this->call($Engine, '_processMarkup', [$results, 'none']);

        $this->assertSame('And God said, *Let* there be **light**', $processed['kjv'][0]->text);
    }

    public function testProcessMarkupWalksEveryBibleAndEveryVerse(): void
    {
        $Engine = $this->engine(EngineV2::class);

        $results = [];

        foreach (['kjv', 'asv'] as $module) {
            foreach (['<script>a</script>one', '<script>b</script>two'] as $text) {
                $Verse = new \stdClass();
                $Verse->text = $text;
                $results[$module][] = $Verse;
            }
        }

        $processed = $this->call($Engine, '_processMarkup', [$results, 'none']);

        foreach ($processed as $module => $verses) {
            foreach ($verses as $Verse) {
                $this->assertStringNotContainsString('<script>', $Verse->text, $module);
            }
        }

        $this->assertSame('one', $processed['kjv'][0]->text);
        $this->assertSame('two', $processed['asv'][1]->text);
    }

    /**
     * The purifier rewrites a bare '&' to '&amp;', which would change v2's verse text for
     * 4,480 Bishops and 3,535 Geneva verses and shift every 'italics' character offset past
     * the ampersand. _processMarkup() holds them out across the whole sanitize chain.
     */
    public function testProcessMarkupKeepsBareAmpersandsInVerseText(): void
    {
        $text = 'And the earth was without fourme, and was voyde: & darknes was <b>vpon</b> the face';

        $v2 = $this->call($this->engine(EngineV2::class), '_processMarkup', [$this->results($text), 'none']);
        $v3 = $this->call($this->engine(EngineV3::class), '_processMarkup', [$this->results($text), 'none']);

        $this->assertSame('And the earth was without fourme, and was voyde: & darknes was <b>vpon</b> the face', $v2['kjv'][0]->text);
        $this->assertSame('And the earth was without fourme, and was voyde: & darknes was **vpon** the face', $v3['kjv'][0]->text);
    }

    /** Holding the ampersands out must not let anything else through with them. */
    public function testProcessMarkupStillStripsScriptsAlongsideAnAmpersand(): void
    {
        $processed = $this->call(
            $this->engine(EngineV2::class),
            '_processMarkup',
            [$this->results('a & b <script>alert(1)</script> c'), 'none']
        );

        $this->assertStringNotContainsString('<script', $processed['kjv'][0]->text);
        $this->assertStringNotContainsString('alert(1)', $processed['kjv'][0]->text);
        $this->assertStringContainsString('a & b', $processed['kjv'][0]->text);
    }

    /**
     * The Markdown converter escapes '[' and ']' - the very markers 'raw' exists to expose,
     * so Psalms 23:1 was coming back as 'The LORD \[is\] my shepherd'. v3 undoes that for raw
     * mode only; the carets and Strong's braces are never escaped.
     */
    public function testProcessMarkupRawKeepsTheBibleMarkersUnescapedOnV3(): void
    {
        $text = 'The LORD [is] my shepherd ‹Let› {H1961}';

        $processed = $this->call($this->engine(EngineV3::class), '_processMarkup', [$this->results($text), 'raw']);

        $this->assertSame('The LORD [is] my shepherd ‹Let› {H1961}', $processed['kjv'][0]->text);
        $this->assertStringNotContainsString('\\[', $processed['kjv'][0]->text);
    }

    /**
     * The brackets are not the only characters the converter escapes - TextConverter also
     * escapes '*', '_' and '\\', and a leading '#'. 'raw' exists to hand back the module's
     * text as it is, so every one of them has to be undone, not just the pair that was
     * noticed first.
     *
     * @param string $text
     */
    #[DataProvider('rawMarkupDataProvider')]
    public function testProcessMarkupRawUndoesEveryMarkdownEscapeOnV3(string $text): void
    {
        $processed = $this->call($this->engine(EngineV3::class), '_processMarkup', [$this->results($text), 'raw']);

        $this->assertSame($text, $processed['kjv'][0]->text);
        $this->assertStringNotContainsString('\\', $processed['kjv'][0]->text);
    }

    public static function rawMarkupDataProvider(): array
    {
        return [
            'square brackets' => ['The LORD [is] my shepherd'],
            'asterisk'        => ['a *marked* word'],
            'underscore'      => ['a _marked_ word'],
            'leading hash'    => ['#1 of the tribe'],
            'both markers'    => ['[is] and *also* and _this_'],
        ];
    }

    /** A backslash in the module's own text survives the round trip as one backslash. */
    public function testProcessMarkupRawKeepsALiteralBackslashOnV3(): void
    {
        $processed = $this->call($this->engine(EngineV3::class), '_processMarkup', [$this->results('a \\ b'), 'raw']);

        $this->assertSame('a \\ b', $processed['kjv'][0]->text);
    }

    /** Only the base class leaves the markers alone already; the undo is v3's. */
    public function testOnlyTheV3EngineOverridesTheMarkupUnescapeHook(): void
    {
        $this->assertSame(Engine::class, (new \ReflectionMethod(EngineV2::class, '_unescapeBibleMarkup'))->getDeclaringClass()->getName());
        $this->assertSame(EngineV3::class, (new \ReflectionMethod(EngineV3::class, '_unescapeBibleMarkup'))->getDeclaringClass()->getName());
    }

    /**
     * _processHtml() takes NULL and answers NULL on every version - an absent column stays
     * absent all the way to the response. Copyright::getProcessedCopyrightStatement() is one
     * hop from that call site and has no return type of its own, so the parameter has to stay
     * nullable too.
     */
    public function testTheProcessHookAnswersNullWithNullOnBothEngines(): void
    {
        $this->assertNull($this->call($this->engine(EngineV2::class), '_processHtml', [NULL]));
        $this->assertNull($this->call($this->engine(EngineV3::class), '_processHtml', [NULL]));
    }

    /** Same contract one level up: the sanitize hook must not invent a value either. */
    public function testTheSanitizeHookAnswersNullWithNullOnBothEngines(): void
    {
        $this->assertNull($this->call($this->engine(EngineV2::class), '_sanitizeHtml', [NULL]));
        $this->assertNull($this->call($this->engine(EngineV3::class), '_sanitizeHtml', [NULL]));
    }

    /** An empty string is a value, not an absent one, and stays an empty string. */
    public function testTheSanitizeHookKeepsTheEmptyStringDistinctFromNull(): void
    {
        $this->assertSame('', $this->call($this->engine(EngineV2::class), '_sanitizeHtml', ['']));
        $this->assertSame('', $this->call($this->engine(EngineV3::class), '_sanitizeHtml', ['']));
    }

    // -----------------------------------------------------------------------
    // _formatStrongs - the Strong's definition fields
    // -----------------------------------------------------------------------

    /**
     * 'entry' and 'root_word' are sanitized by their accessors on App\Models\StrongsDefinition,
     * so by the time _formatStrongs() sees them the whitelist has already run and only the
     * version's own _processHtml() is left to apply - on v2, nothing. 'tvm' has no accessor
     * (the count prefix has to be stripped from the raw column first) and is still sanitized
     * here, so the script in it does not survive.
     */
    public function testFormatStrongsProcessesTheHtmlBearingFields(): void
    {
        $Engine = $this->engine(EngineV2::class);

        $formatted = $this->call($Engine, '_formatStrongs', [[
            'id'         => 1234,
            'number'     => 'H1234',
            'root_word'  => '<span>בּקע</span>',
            'tvm'        => '<b>Count:</b> 12 total<br>a <b>verb</b><script>alert(1)</script>',
            'entry'      => 'to <i>cleave</i>',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]]);

        // <span> is on the widened whitelist (Helpers::SANITIZE_HTML_ALLOWED) and survives.
        $this->assertSame('<span>בּקע</span>', $formatted['root_word']);
        $this->assertSame('to <i>cleave</i>', $formatted['entry']);
        $this->assertSame('a <b>verb</b>', $formatted['tvm']);
    }

    /**
     * Strong's definitions are imported HTML like every other field, so they go through the
     * engine's hook rather than the sanitizer directly - otherwise v3 keeps answering with the
     * HTML the version exists to avoid.
     */
    public function testFormatStrongsReturnsMarkdownOnTheV3Engine(): void
    {
        $Engine = $this->engine(EngineV3::class);

        $formatted = $this->call($Engine, '_formatStrongs', [[
            'root_word' => '<span>בּקע</span>',
            'tvm'       => '<b>Count:</b> 12 total<br>a <b>verb</b>',
            'entry'     => 'to <i>cleave</i>',
        ]]);

        $this->assertSame('to *cleave*', $formatted['entry']);
        $this->assertSame('a **verb**', $formatted['tvm']);
        $this->assertSame('בּקע', $formatted['root_word']);
    }

    /** The count prefix is stripped before sanitizing, and must stay stripped. */
    public function testFormatStrongsStillRemovesTheCountFromTvm(): void
    {
        $Engine = $this->engine(EngineV2::class);

        $formatted = $this->call($Engine, '_formatStrongs', [[
            'root_word' => '',
            'tvm'       => '<b>Count:</b> 12 total<br>a <b>verb</b>',
            'entry'     => '',
        ]]);

        $this->assertStringNotContainsString('Count:', $formatted['tvm']);
    }

    /**
     * 14,248 of the 14,696 definitions have no TVM, and the field has always come back as
     * NULL. Sanitizing it must not turn that into an empty string - '/api/strongs' is reached
     * through the legacy route that exists for backward compatibility.
     */
    public function testFormatStrongsReturnsNullForAMissingTvm(): void
    {
        $Engine = $this->engine(EngineV2::class);

        $formatted = $this->call($Engine, '_formatStrongs', [['root_word' => '', 'tvm' => null, 'entry' => 'x']]);

        $this->assertNull($formatted['tvm']);
    }

    public function testFormatStrongsDropsTheTimestamps(): void
    {
        $Engine = $this->engine(EngineV2::class);

        $formatted = $this->call($Engine, '_formatStrongs', [[
            'root_word'  => '',
            'tvm'        => null,
            'entry'      => 'x',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]]);

        $this->assertArrayNotHasKey('created_at', $formatted);
        $this->assertArrayNotHasKey('updated_at', $formatted);
    }
}
