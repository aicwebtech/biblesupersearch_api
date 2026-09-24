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
    // _processMarkup / _processBibleText - verse text
    // -----------------------------------------------------------------------

    /**
     * Verse text is not an HTML document and is no longer treated as one: _processBibleText()
     * strips tags rather than purifying, so nothing re-encodes the text on the way past.
     *
     * The Bible's own markers are the module's, not HTML - '[]' for added words, '{}' for a
     * Strong's number, '‹›' for red letter - and 'none' is the mode that takes them out.
     */
    public function testProcessMarkupStripsBibleMarkupAndHtmlFromVerseText(): void
    {
        $Engine  = $this->engine(EngineV2::class);
        $results = $this->results('And God <b>said</b>, ‹Let› [there] be {H1961} light<script>alert(1)</script>');

        $processed = $this->call($Engine, '_processMarkup', [$results, 'none']);

        $this->assertSame('And God said, Let there be  lightalert(1)', $processed['kjv'][0]->text);
    }

    /** 'safe' keeps the module's own markers, and still takes the HTML out. */
    public function testProcessMarkupSafeKeepsBibleMarkupButStillStripsHtml(): void
    {
        $Engine  = $this->engine(EngineV2::class);
        $results = $this->results('And God <b>said</b>, ‹Let› [there] be {H1961} light<script>alert(1)</script>');

        $processed = $this->call($Engine, '_processMarkup', [$results, 'safe']);

        $text = $processed['kjv'][0]->text;

        $this->assertStringContainsString('‹Let›', $text);
        $this->assertStringContainsString('[there]', $text);
        $this->assertStringContainsString('{H1961}', $text);
        $this->assertStringNotContainsString('<b>', $text);
        $this->assertStringNotContainsString('<script>', $text);
    }

    /**
     * 'raw' is the text exactly as stored - nothing is stripped, not even HTML.
     *
     * A module imported with --rawtext keeps its source markup in the column
     * (ImporterAbstract::$raw_format), and 'raw' is the only mode that can hand that back. It
     * briefly ran strip_tags() over this path too, which silently deleted every '<...>' a
     * raw-imported module carried.
     *
     * v2 only - see testProcessMarkupRawIsSafeOnV3().
     */
    public function testProcessMarkupRawReturnsTheTextVerbatim(): void
    {
        $text = 'And God <b>said</b>, ‹Let› [there] be {H1961} light<script>alert(1)</script>';

        $processed = $this->call($this->engine(EngineV2::class), '_processMarkup', [$this->results($text), 'raw']);

        $this->assertSame($text, $processed['kjv'][0]->text);
    }

    /**
     * On v3 'raw' is an alias of 'safe', because v3 answers in Markdown and the stored column
     * of a --rawtext module is HTML - handing it back verbatim would put HTML in a Markdown
     * response, which is what EngineV3 narrows HIGHLIGHT_TAG_WHITELIST to prevent for the
     * highlight tag.
     *
     * The mode is still accepted rather than refused, so a v2 client moving to v3 keeps
     * working; it just gets the most a Markdown response can carry.
     */
    public function testProcessMarkupRawIsSafeOnV3(): void
    {
        $text   = 'And God <b>said</b>, ‹Let› [there] be {H1961} light<script>alert(1)</script>';
        $Engine = $this->engine(EngineV3::class);

        $raw = $this->call($Engine, '_processMarkup', [$this->results($text), 'raw'])['kjv'][0]->text;

        $this->assertSame(
            $this->call($Engine, '_processMarkup', [$this->results($text), 'safe'])['kjv'][0]->text,
            $raw
        );

        $this->assertStringNotContainsString('<b>', $raw);
        $this->assertStringNotContainsString('<script>', $raw);

        // Safe, not none: the module's own markers are not HTML and still come through.
        $this->assertStringContainsString('‹Let›', $raw);
        $this->assertStringContainsString('[there]', $raw);
        $this->assertStringContainsString('{H1961}', $raw);
    }

    /** Which holds however the mode is spelled, since the alias is applied after lowercasing. */
    #[DataProvider('mixedCaseRawDataProvider')]
    public function testProcessMarkupRawIsSafeOnV3WhateverTheCasing(string $mode): void
    {
        $text   = 'And God <b>said</b>, ‹Let› [there] be {H1961} light';
        $Engine = $this->engine(EngineV3::class);

        $this->assertSame(
            $this->call($Engine, '_processMarkup', [$this->results($text), 'safe'])['kjv'][0]->text,
            $this->call($Engine, '_processMarkup', [$this->results($text), $mode])['kjv'][0]->text,
            $mode
        );
    }

    public static function mixedCaseRawDataProvider(): array
    {
        return [
            'upper' => ['RAW'],
            'title' => ['Raw'],
            'mixed' => ['rAw'],
        ];
    }

    /**
     * Nothing outside the whitelist reaches a mode branch: it resolves to 'none', the most
     * restrictive one, rather than falling through to 'safe' at the bottom of the method.
     *
     * The check reads static::MARKUP_MODE_WHITELIST, so a version that narrows the whitelist
     * narrows what it accepts here too.
     *
     * @param mixed $mode
     */
    #[DataProvider('unrecognisedModeDataProvider')]
    public function testProcessMarkupTreatsAnUnrecognisedModeAsNone(mixed $mode): void
    {
        $text   = 'And God <b>said</b>, ‹Let› [there] be {H1961} light';
        $Engine = $this->engine(EngineV2::class);

        $this->assertSame(
            $this->call($Engine, '_processMarkup', [$this->results($text), 'none'])['kjv'][0]->text,
            $this->call($Engine, '_processMarkup', [$this->results($text), $mode])['kjv'][0]->text,
            var_export($mode, TRUE)
        );
    }

    public static function unrecognisedModeDataProvider(): array
    {
        return [
            'typo'         => ['safety'],
            'unknown mode' => ['html'],
            'empty string' => [''],
            'null'         => [NULL],
        ];
    }

    /**
     * The three modes differ only in what they take out, and each takes out strictly more
     * than the one before it.
     */
    public function testTheModesAreOrderedByHowMuchTheyRemove(): void
    {
        $text   = 'And God <b>said</b>, ‹Let› [there] be {H1961} light';
        $Engine = $this->engine(EngineV2::class);

        $raw  = $this->call($Engine, '_processMarkup', [$this->results($text), 'raw'])['kjv'][0]->text;
        $safe = $this->call($Engine, '_processMarkup', [$this->results($text), 'safe'])['kjv'][0]->text;
        $none = $this->call($Engine, '_processMarkup', [$this->results($text), 'none'])['kjv'][0]->text;

        $this->assertGreaterThan(strlen($safe), strlen($raw));
        $this->assertGreaterThan(strlen($none), strlen($safe));

        // The markers are what separates 'safe' from 'none'; the HTML is what separates
        // 'raw' from 'safe'.
        $this->assertStringContainsString('<b>', $raw);
        $this->assertStringNotContainsString('<b>', $safe);
        $this->assertStringContainsString('[there]', $safe);
        $this->assertStringNotContainsString('[there]', $none);
    }

    /**
     * The modes the 'markup' parameter accepts, pinned against _processMarkup()'s branches.
     *
     * 'none' has to be first: it is the default, and Engine::_sanitizeInput() answers a
     * rejected value with the default rather than with the whitelist's own first entry.
     */
    public function testTheMarkupModeWhitelistNamesEveryMode(): void
    {
        $this->assertSame(['none', 'safe', 'raw'], Engine::MARKUP_MODE_WHITELIST);
        $this->assertSame('none', Engine::MARKUP_MODE_WHITELIST[0]);
    }

    /**
     * Every mode on the whitelist does something a caller can tell apart from the others -
     * a mode that fell through to another's branch would be a documented option that does
     * not exist.
     */
    public function testEveryWhitelistedModeIsDistinct(): void
    {
        $text   = 'And God <b>said</b>, ‹Let› [there] be {H1961} light';
        $Engine = $this->engine(EngineV2::class);

        $outputs = [];

        foreach (Engine::MARKUP_MODE_WHITELIST as $mode) {
            $outputs[$mode] = $this->call($Engine, '_processMarkup', [$this->results($text), $mode])['kjv'][0]->text;
        }

        $this->assertSame($outputs, array_unique($outputs));
    }

    /**
     * Verse text is now the same on both versions.
     *
     * It used to differ: v2 purified it and v3 converted it to Markdown, so a module's '<i>'
     * became '*...*' on v3 and survived on v2. Neither happens now - the text is stripped of
     * tags and handed back as it stands - so the two engines agree, and the version only
     * decides what the highlighter wraps a match with.
     */
    public function testVerseTextIsTheSameOnBothVersions(): void
    {
        $text = 'And God said, <i>Let</i> there be <b>light</b>';

        $v2 = $this->call($this->engine(EngineV2::class), '_processMarkup', [$this->results($text), 'none']);
        $v3 = $this->call($this->engine(EngineV3::class), '_processMarkup', [$this->results($text), 'none']);

        $this->assertSame('And God said, Let there be light', $v2['kjv'][0]->text);
        $this->assertSame($v2['kjv'][0]->text, $v3['kjv'][0]->text);
    }

    public function testProcessMarkupWalksEveryBibleAndEveryVerse(): void
    {
        $Engine = $this->engine(EngineV2::class);

        $results = [];

        foreach (['kjv', 'asv'] as $module) {
            foreach (['<b>a</b>one', '<b>b</b>two'] as $text) {
                $Verse = new \stdClass();
                $Verse->text = $text;
                $results[$module][] = $Verse;
            }
        }

        $processed = $this->call($Engine, '_processMarkup', [$results, 'none']);

        foreach ($processed as $module => $verses) {
            foreach ($verses as $Verse) {
                $this->assertStringNotContainsString('<b>', $Verse->text, $module);
            }
        }

        $this->assertSame('aone', $processed['kjv'][0]->text);
        $this->assertSame('btwo', $processed['asv'][1]->text);
    }

    /**
     * A bare '&' survives untouched, on both versions.
     *
     * 8,015 verses in the installed Bibles carry one. The purifier used to rewrite it to
     * '&amp;', which moved every 'italics' character offset past it and made a client
     * italicize the wrong span - so the ampersands had to be held out of the sanitize chain
     * and put back afterwards. strip_tags() does not encode anything, so there is nothing to
     * hold out any more and Helpers no longer carries the sentinel that did it.
     */
    public function testProcessMarkupKeepsBareAmpersandsInVerseText(): void
    {
        $text = 'And the earth was without fourme, and was voyde: & darknes was vpon the face';

        foreach ([EngineV2::class, EngineV3::class] as $class) {
            $processed = $this->call($this->engine($class), '_processMarkup', [$this->results($text), 'none']);

            $this->assertSame($text, $processed['kjv'][0]->text, $class);
        }
    }

    /** An existing entity is left as it is too - nothing decodes or re-encodes the text. */
    public function testProcessMarkupLeavesEntitiesAlone(): void
    {
        $text = 'Alpha &amp; Omega &#8212; the first';

        $processed = $this->call($this->engine(EngineV2::class), '_processMarkup', [$this->results($text), 'none']);

        $this->assertSame($text, $processed['kjv'][0]->text);
    }

    /**
     * _processBibleText() takes every tag out and keeps none.
     *
     * It runs from _processMarkup(), which is called before _highlightResults(), so there is
     * no highlight markup in the text yet for it to preserve - the highlighter adds the tag
     * afterwards, and Helpers::buildHighlightTags() is what decides that tag is safe.
     *
     * The text of a script survives as text, which is what strip_tags() does. Nothing in verse
     * text is HTML in the first place, so there is no document for the tag to belong to.
     */
    public function testProcessBibleTextStripsEveryTag(): void
    {
        $Engine = $this->engine(EngineV2::class);

        $this->assertSame(
            'the men are shepherdsalert(1)',
            $this->call($Engine, '_processBibleText', ['the <b>men</b> are <i>shepherds</i><script>alert(1)</script>'])
        );
    }

    /** Text with nothing to strip comes back byte for byte. */
    public function testProcessBibleTextLeavesPlainTextAlone(): void
    {
        $text = 'And God said, Let there be light: & there was light.';

        $this->assertSame($text, $this->call($this->engine(EngineV2::class), '_processBibleText', [$text]));
    }

    /** The hook takes text and nothing else - the highlight tag is resolved elsewhere. */
    public function testProcessBibleTextTakesOnlyTheText(): void
    {
        $Method = new \ReflectionMethod(EngineV2::class, '_processBibleText');

        $this->assertCount(1, $Method->getParameters());
        $this->assertSame('text', $Method->getParameters()[0]->getName());
    }

    // -----------------------------------------------------------------------
    // highlight_tag - the per-version whitelist
    // -----------------------------------------------------------------------

    /**
     * The tag is emitted into verse text after every other tag has been stripped out of it,
     * and nothing escapes it, so the whitelist is what stands between a caller and an
     * injected element.
     */
    public function testTheV2WhitelistCarriesElementNamesAndMarkers(): void
    {
        $whitelist = EngineV2::HIGHLIGHT_TAG_WHITELIST;

        foreach (['b', 'i', 'em', 'strong'] as $element) {
            $this->assertContains($element, $whitelist, $element);
        }

        foreach (['*', '**', '__', '`'] as $marker) {
            $this->assertContains($marker, $whitelist, $marker);
        }
    }

    /** v3 answers in Markdown, so an element name is not one of its options. */
    public function testTheV3WhitelistCarriesMarkersOnly(): void
    {
        $whitelist = EngineV3::HIGHLIGHT_TAG_WHITELIST;

        $this->assertSame(\App\Helpers::HIGHLIGHT_PLAIN_TEXT_MARKERS, $whitelist);

        foreach (['b', 'i', 'em', 'strong', 'span'] as $element) {
            $this->assertNotContains($element, $whitelist, $element);
        }
    }

    /** Neither whitelist may carry something that could open an element of its own. */
    public function testNoWhitelistedTagCarriesMarkup(): void
    {
        foreach ([EngineV2::class, EngineV3::class] as $class) {
            foreach ($class::HIGHLIGHT_TAG_WHITELIST as $tag) {
                $this->assertDoesNotMatchRegularExpression('/[<>&"\'\\\\]/', $tag, $class . ': ' . $tag);
            }
        }
    }

    /** Every whitelisted tag resolves to a usable pair rather than to the fallback. */
    public function testEveryWhitelistedTagResolvesToItself(): void
    {
        foreach (EngineV2::HIGHLIGHT_TAG_WHITELIST as $tag) {
            list($pre, $post) = \App\Helpers::buildHighlightTags($tag);

            $this->assertStringContainsString($tag, $pre, $tag);
            $this->assertStringContainsString($tag, $post, $tag);
        }
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

    /**
     * A value that reaches the API already purified against the wider editor allowlist is
     * narrowed back to what the API documents rather than emitted as it stands. An image
     * stored in a description must not reach the response just because an administrator is
     * allowed to put one there.
     *
     * This is the SANITIZE_HTML_ALLOWED half of that; the columns actionBibles() emits take
     * the editor-strict half - see testTheEditorSanitizeHookNarrowsEditorMarkupBackOut().
     */
    public function testTheSanitizeHookNarrowsEditorMarkupBackOut(): void
    {
        $editor = \App\Helpers::sanitizeEditorHtml('<p>Desc</p><img src="/logo.png"><hr><code>x</code>');

        $this->assertStringContainsString('<img', $editor, 'The editor allowlist should have kept the image');

        $v2 = $this->call($this->engine(EngineV2::class), '_sanitizeHtml', [$editor]);

        $this->assertStringContainsString('Desc', $v2);
        $this->assertStringNotContainsString('<img', $v2);
        $this->assertStringNotContainsString('<hr', $v2);
        $this->assertStringNotContainsString('<code', $v2);
    }

    /**
     * The hook actionBibles() puts its editor-written columns through. Narrower than the
     * editor allowlist - the image goes - and wider than SANITIZE_HTML_ALLOWED, because a
     * copyright statement written in the admin editor is meant to keep its formatting.
     */
    public function testTheEditorSanitizeHookNarrowsEditorMarkupBackOut(): void
    {
        $editor = \App\Helpers::sanitizeEditorHtml('<p>Desc</p><img src="/logo.png"><hr><s>struck</s>');

        $this->assertStringContainsString('<img', $editor, 'The editor allowlist should have kept the image');

        $v2 = $this->call($this->engine(EngineV2::class), '_sanitizeEditorHtml', [$editor]);

        $this->assertStringContainsString('Desc', $v2);
        $this->assertStringNotContainsString('<img', $v2);
        $this->assertStringContainsString('<hr', $v2, 'The rule is on the strict allowlist');
        $this->assertStringContainsString('<s>struck</s>', $v2);
    }

    /** v3 answers the same columns in Markdown, and the dropped image leaves no '![]' behind. */
    public function testTheEditorSanitizeHookAnswersMarkdownOnV3(): void
    {
        $markdown = $this->call($this->engine(EngineV3::class), '_sanitizeEditorHtml', [
            '<p>Desc</p><img src="/logo.png" alt="Logo"><p><strong>Bold</strong></p>',
        ]);

        $this->assertStringContainsString('**Bold**', $markdown);
        $this->assertStringNotContainsString('![', $markdown);
        $this->assertStringNotContainsString('/logo.png', $markdown);
        $this->assertStringNotContainsString('<p>', $markdown);
    }

    /** Same NULL contract as the other two hooks - an absent column must not become a string. */
    public function testTheEditorSanitizeHookAnswersNullWithNullOnBothEngines(): void
    {
        $this->assertNull($this->call($this->engine(EngineV2::class), '_sanitizeEditorHtml', [NULL]));
        $this->assertNull($this->call($this->engine(EngineV3::class), '_sanitizeEditorHtml', [NULL]));
    }

    /**
     * An empty column is absent, not present-and-empty: Helpers::sanitizeHtml() answers NULL
     * for it, so the hook does too and the response reports null either way.
     */
    public function testTheSanitizeHookAnswersTheEmptyStringWithNull(): void
    {
        $this->assertNull($this->call($this->engine(EngineV2::class), '_sanitizeHtml', ['']));
        $this->assertNull($this->call($this->engine(EngineV3::class), '_sanitizeHtml', ['']));
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
