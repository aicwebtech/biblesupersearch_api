<?php

namespace Tests\Feature;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Engines\EngineV2;
use App\Engines\EngineV3;

/**
 * BSS-290: every HTML-bearing field in an API response is now routed through the engine's
 * _sanitizeHtml() hook - whitelisted HTML on v2, Markdown on v3.
 *
 * The sanitizer itself is pinned in tests/Unit/Helpers/SanitizeHtmlTest.php and the hook in
 * tests/Unit/Engines/EngineVersionTest.php; what is checked here is that the real actions
 * actually call it, against the installed content. Read-only - nothing is written.
 */
class EngineSanitizationTest extends TestCase
{
    /**
     * Asserts the string carries no HTML element tags.
     *
     * Deliberately not strip_tags(): a Markdown autolink is written '<http://example.com>',
     * which strip_tags() eats but which is Markdown, not markup. An element tag is a '<'
     * followed by a name and then whitespace or '>', which an autolink's URI scheme is not.
     */
    private function assertContainsNoHtmlTags(string $string, string $message = ''): void
    {
        $this->assertDoesNotMatchRegularExpression('/<\/?[a-zA-Z][a-zA-Z0-9]*\s*\/?>/', $string, $message);
    }

    /** The modules whose $field carries HTML, keyed by module. */
    private function modulesWithHtml(array $bibles, string $field): array
    {
        $modules = [];

        foreach($bibles as $module => $bible) {
            $value = $bible[$field] ?? '';

            if($value && strip_tags($value) !== $value) {
                $modules[$module] = $value;
            }
        }

        return $modules;
    }

    // -----------------------------------------------------------------------
    // actionBibles - copyright statements
    // -----------------------------------------------------------------------

    /**
     * Copyright statements are author-supplied HTML imported with the module, so they are the
     * one field in the Bible listing that could carry a script. Nothing outside the whitelist
     * may survive the listing.
     */
    public function testCopyrightStatementsAreWhitelistedHtmlOnV2(): void
    {
        $bibles = (new EngineV2())->actionBibles([]);

        $this->assertNotEmpty($bibles);

        foreach($bibles as $module => $bible) {
            $statement = $bible['copyright_statement'] ?? '';

            if(!$statement) {
                continue;
            }

            foreach(['<script', '<iframe', '<style', 'onerror=', 'onclick=', 'javascript:'] as $vector) {
                $this->assertStringNotContainsStringIgnoringCase($vector, $statement, $module);
            }
        }
    }

    public function testCopyrightStatementsAreMarkdownOnV3(): void
    {
        $v2 = (new EngineV2())->actionBibles([]);
        $html = $this->modulesWithHtml($v2, 'copyright_statement');

        if(empty($html)) {
            $this->markTestSkipped('No installed Bible has HTML in its copyright statement');
        }

        $v3 = (new EngineV3())->actionBibles([]);

        foreach($html as $module => $statement) {
            $markdown = $v3[$module]['copyright_statement'];

            $this->assertNotSame($statement, $markdown, $module . ': v3 must not repeat the v2 HTML verbatim');
            $this->assertContainsNoHtmlTags($markdown, $module);
        }
    }

    // -----------------------------------------------------------------------
    // actionBibles - descriptions
    // -----------------------------------------------------------------------

    /**
     * Descriptions are imported with the module and several carry a whole HTML document,
     * <head> and all. The listing selects the column unconditionally, so the field ships on
     * every response and has to be sanitized on the way out.
     */
    public function testDescriptionsAreWhitelistedHtmlOnV2(): void
    {
        $bibles = (new EngineV2())->actionBibles([]);

        $this->assertNotEmpty($bibles);

        foreach($bibles as $module => $bible) {
            $this->assertArrayHasKey('description', $bible, $module);

            $description = $bible['description'] ?? '';

            if(!$description) {
                continue;
            }

            foreach(['<html', '<head', '<meta', '<body', '<script', 'onerror=', 'javascript:'] as $vector) {
                $this->assertStringNotContainsStringIgnoringCase($vector, $description, $module);
            }
        }
    }

    public function testDescriptionsAreMarkdownOnV3(): void
    {
        $v2 = (new EngineV2())->actionBibles([]);
        $html = $this->modulesWithHtml($v2, 'description');

        if(empty($html)) {
            $this->markTestSkipped('No installed Bible has HTML in its description');
        }

        $v3 = (new EngineV3())->actionBibles([]);

        foreach($html as $module => $description) {
            $markdown = $v3[$module]['description'];

            $this->assertNotSame($description, $markdown, $module);
            $this->assertContainsNoHtmlTags($markdown, $module);
        }
    }

    /**
     * The narrowing the listing performs, pinned to the hook rather than to the data.
     *
     * A description is administrator-editable, so it is purified against
     * SANITIZE_EDITOR_HTML_ALLOWED on the way into the column - and that allowlist keeps an
     * image. Several shipped modules carry the publisher's certification badge that way
     * ('<img src="eBible.org_certified.jpg">' in the IRV description), so emitting the column
     * as it stands makes every consumer that renders /api/bibles fetch a third-party URL.
     *
     * Asserted through a subclass that tags the hook's output, because whether an *installed*
     * Bible happens to have an image in its description varies by deployment - the wiring is
     * what has to hold. actionBibles() used to route the field through _processHtml(), which
     * is the identity on v2, and the field shipped as the editor left it.
     */
    public function testTheListingRoutesDescriptionsThroughTheEditorSanitizeHook(): void
    {
        $Engine = new class extends EngineV2 {
            protected function _sanitizeEditorHtml(?string $html): ?string
            {
                $sanitized = parent::_sanitizeEditorHtml($html);

                return ($sanitized === NULL) ? NULL : '[hook]' . $sanitized;
            }
        };

        $bibles = $Engine->actionBibles([]);
        $tagged = 0;

        $this->assertNotEmpty($bibles);

        foreach($bibles as $module => $bible) {
            $description = $bible['description'] ?? NULL;

            if($description === NULL) {
                continue;
            }

            $this->assertStringStartsWith('[hook]', $description, $module . ': the description bypassed _sanitizeEditorHtml()');
            $tagged++;
        }

        $this->assertGreaterThan(0, $tagged, 'No installed Bible reported a description');
    }

    /**
     * And what that narrowing is for: nothing in the listing may make the consumer fetch a
     * third party. An <img> is on the editor allowlist and off the strict one.
     */
    public function testNoListedFieldCarriesARemoteResource(): void
    {
        $bibles = (new EngineV2())->actionBibles([]);

        $this->assertNotEmpty($bibles);

        foreach($bibles as $module => $bible) {
            foreach(['description', 'copyright_statement'] as $field) {
                $value = (string) ($bible[$field] ?? '');

                if($value === '') {
                    continue;
                }

                foreach(['<img', '<iframe', '<object', '<embed', 'background-image'] as $vector) {
                    $this->assertStringNotContainsStringIgnoringCase($vector, $value, $module . '.' . $field);
                }
            }
        }
    }

    /** The same fields on v3, where an image would arrive as a Markdown '![alt](src)'. */
    public function testNoListedFieldCarriesARemoteResourceOnV3(): void
    {
        $bibles = (new EngineV3())->actionBibles([]);

        $this->assertNotEmpty($bibles);

        foreach($bibles as $module => $bible) {
            foreach(['description', 'copyright_statement'] as $field) {
                $value = (string) ($bible[$field] ?? '');

                $this->assertStringNotContainsString('![', $value, $module . '.' . $field);
            }
        }
    }

    // -----------------------------------------------------------------------
    // actionQuery - verse text
    // -----------------------------------------------------------------------

    /** The verse text Genesis 1:2 is stored with, markers and all. */
    private function genesisOneTwo(string $markup = NULL): string
    {
        $Engine = new EngineV2();

        $input = ['bible' => 'kjv', 'request' => 'Genesis 1:2', 'data_format' => 'raw'];

        if($markup !== NULL) {
            $input['markup'] = $markup;
        }

        $results = $Engine->actionQuery($input);

        $this->assertFalse($Engine->hasErrors(), (string) $markup);

        return $results['kjv'][0]->text;
    }

    /**
     * 'raw' hands back the Bible's own markup - the bracketed added words the renderers turn
     * into italics - or every raw consumer silently loses it.
     */
    public function testRawMarkupIsReturnedAsStored(): void
    {
        $this->assertStringContainsString('[was]', $this->genesisOneTwo('raw'));
    }

    /** 'safe' keeps those markers too; it only takes HTML out from around them. */
    public function testSafeMarkupKeepsTheBibleMarkers(): void
    {
        $this->assertStringContainsString('[was]', $this->genesisOneTwo('safe'));
    }

    /** The default markup mode still strips that same markup. */
    public function testDefaultMarkupModeStillStripsBibleMarkup(): void
    {
        $text = $this->genesisOneTwo();

        $this->assertStringNotContainsString('[was]', $text);
        $this->assertStringContainsString('was upon the face of the deep', $text);
    }

    /** Named explicitly, 'none' is the same as not asking. */
    public function testNoneIsTheSameAsTheDefault(): void
    {
        $this->assertSame($this->genesisOneTwo(), $this->genesisOneTwo('none'));
    }

    /**
     * An unrecognised mode falls back to 'none' rather than to whichever branch of
     * _processMarkup() happens to catch it.
     *
     * Without the whitelist a typo reached the default branch of the mode check, which is the
     * permissive one - 'markup=non' would have kept the markers a caller plainly did not want
     * and nothing would have said so.
     *
     * @param string $markup
     */
    #[DataProvider('unrecognisedMarkupModeDataProvider')]
    public function testAnUnrecognisedMarkupModeFallsBackToNone(string $markup): void
    {
        $this->assertSame($this->genesisOneTwo(), $this->genesisOneTwo($markup), $markup);
    }

    public static function unrecognisedMarkupModeDataProvider(): array
    {
        return [
            'typo for none'  => ['non'],
            'typo for raw'   => ['raws'],
            'typo for safe'  => ['safety'],
            'unknown mode'   => ['html'],
            'empty'          => [''],
        ];
    }

    /**
     * A mode has no casing of its own, and both halves of the check agree on that.
     *
     * Engine::_applyWhitelist() compares with strcasecmp() and hands back the caller's own
     * spelling, so 'RAW' is accepted at validation - and _processMarkup() used to compare it
     * case-sensitively, which dropped it through to the 'safe' branch. Accepted at one end
     * and unrecognised at the other is the one combination that cannot be right.
     *
     * @param string $markup
     */
    #[DataProvider('mixedCaseMarkupModeDataProvider')]
    public function testTheMarkupModeIsCaseInsensitive(string $markup): void
    {
        $this->assertSame(
            $this->genesisOneTwo(strtolower($markup)),
            $this->genesisOneTwo($markup),
            $markup
        );
    }

    public static function mixedCaseMarkupModeDataProvider(): array
    {
        return [
            'upper raw'  => ['RAW'],
            'title raw'  => ['Raw'],
            'upper safe' => ['SAFE'],
            'upper none' => ['NONE'],
        ];
    }

    /** And uppercase 'RAW' is the raw text, not the safe text. */
    public function testUppercaseRawIsTheRawText(): void
    {
        $this->assertSame($this->genesisOneTwo('raw'), $this->genesisOneTwo('RAW'));
        $this->assertStringContainsString('[was]', $this->genesisOneTwo('RAW'));
    }

    /** Every documented mode is accepted, and none of them raises an error. */
    public function testEveryWhitelistedMarkupModeIsAccepted(): void
    {
        foreach(EngineV2::MARKUP_MODE_WHITELIST as $mode) {
            $this->assertNotSame('', $this->genesisOneTwo($mode), $mode);
        }
    }

    // -----------------------------------------------------------------------
    // actionQuery - verse text on v3, where 'raw' is an alias of 'safe'
    // -----------------------------------------------------------------------

    /** Genesis 1:2 as v3 answers it. */
    private function genesisOneTwoOnV3(string $markup = NULL): string
    {
        $Engine = new EngineV3();

        $input = ['bible' => 'kjv', 'request' => 'Genesis 1:2', 'data_format' => 'raw'];

        if($markup !== NULL) {
            $input['markup'] = $markup;
        }

        $results = $Engine->actionQuery($input);

        $this->assertFalse($Engine->hasErrors(), (string) $markup);

        return $results['kjv'][0]->text;
    }

    /**
     * v3 answers in Markdown, so 'raw' - the one mode that hands the stored column back
     * untouched - resolves to 'safe' there instead. The column of a module imported with
     * --rawtext is HTML, and on v3 that would be HTML in a Markdown response; no installed
     * module stores tags in its verse text, so what that resolution actually drops is pinned
     * in tests/Unit/Engines/EngineVersionTest.php against a synthetic verse.
     */
    public function testRawMarkupIsSafeMarkupOnV3(): void
    {
        $this->assertSame($this->genesisOneTwoOnV3('safe'), $this->genesisOneTwoOnV3('raw'));
    }

    /** And it is 'safe', not 'none': the module's own markers are not HTML and still come through. */
    public function testRawMarkupOnV3StillKeepsTheBibleMarkers(): void
    {
        $text = $this->genesisOneTwoOnV3('raw');

        $this->assertStringContainsString('[was]', $text);
        $this->assertNotSame($this->genesisOneTwoOnV3('none'), $text);
    }

    /** The mode is accepted rather than refused, so a v2 client moving to v3 keeps working. */
    public function testEveryWhitelistedMarkupModeIsAcceptedOnV3(): void
    {
        foreach(EngineV3::MARKUP_MODE_WHITELIST as $mode) {
            $this->assertNotSame('', $this->genesisOneTwoOnV3($mode), $mode);
        }
    }

    /** No mode on v3 answers with an HTML tag. */
    public function testNoMarkupModeReturnsHtmlOnV3(): void
    {
        foreach(EngineV3::MARKUP_MODE_WHITELIST as $mode) {
            $this->assertContainsNoHtmlTags($this->genesisOneTwoOnV3($mode), $mode);
        }
    }

    // -----------------------------------------------------------------------
    // Highlighting
    // -----------------------------------------------------------------------

    /**
     * Highlighting runs after the sanitizer, so the highlight tag is emitted as-is. The tag
     * itself is whatever the caller asked for, and BSS-290 added a branch to
     * SqlSearch::highlightResults() that must not disturb the ordinary bare-name tags.
     */
    public function testTheDefaultHighlightTagIsStillWrapped(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery([
            'bible'       => 'kjv',
            'search'      => 'faith',
            'highlight'   => 1,
            'data_format' => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());

        $tag = config('bss.defaults.highlight_tag');
        $this->assertStringContainsString('<' . $tag . '>faith', $results['kjv'][0]->text);
        $this->assertStringContainsString('</' . $tag . '>', $results['kjv'][0]->text);
    }

    public function testAnExplicitHighlightTagIsStillWrapped(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery([
            'bible'         => 'kjv',
            'search'        => 'faith',
            'highlight'     => 1,
            'highlight_tag' => 'em',
            'data_format'   => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringContainsString('<em>faith', $results['kjv'][0]->text);
        $this->assertStringContainsString('</em>', $results['kjv'][0]->text);
    }

    /**
     * highlight_tag is caller-supplied and is the only tag in the response.
     *
     * _processMarkup() strips every tag out of verse text before the highlighter runs, so an
     * unrestricted tag name would be writing an executable element into text that has just
     * been cleared of markup. Two things refuse it: the version's HIGHLIGHT_TAG_WHITELIST at
     * input validation, and Helpers::buildHighlightTags() when it resolves the pair.
     */
    public function testAnUnsafeHighlightTagIsNotInjectedIntoVerseText(): void
    {
        foreach(['script', 'iframe', 'style', 'svg', 'object', 'img'] as $tag) {
            $Engine  = new EngineV2();
            $results = $Engine->actionQuery([
                'bible'         => 'kjv',
                'search'        => 'faith',
                'highlight'     => 1,
                'highlight_tag' => $tag,
                'data_format'   => 'raw',
            ]);

            $this->assertSame(3, $Engine->getErrorLevel(), $tag); // reported, not refused

            $text = $results['kjv'][0]->text;

            $this->assertStringNotContainsStringIgnoringCase('<' . $tag, $text, $tag . ' was injected');
            $this->assertStringContainsString('<b>faith</b>', $text, $tag . ' did not fall back');
        }
    }

    /**
     * A caller who writes the tag out in full must not get doubled brackets. The input
     * sanitizer strips the angle brackets before the search ever sees them, so the request
     * falls back to the configured tag.
     */
    public function testAnAngleBracketedHighlightTagIsNotDoubleWrapped(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery([
            'bible'         => 'kjv',
            'search'        => 'faith',
            'highlight'     => 1,
            'highlight_tag' => '<b>',
            'data_format'   => 'raw',
        ]);

        $this->assertSame(3, $Engine->getErrorLevel()); // reported, not refused
        $this->assertStringNotContainsString('<<', $results['kjv'][0]->text);
        $this->assertStringNotContainsString('</<', $results['kjv'][0]->text);
        $this->assertStringContainsString('<b>faith', $results['kjv'][0]->text);
    }

    /**
     * A Markdown marker is symmetrical - wrapping it the way an element name is wrapped would
     * emit '<**>', which is neither HTML nor Markdown.
     */
    public function testAMarkdownHighlightTagIsEmittedVerbatim(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery([
            'bible'         => 'kjv',
            'search'        => 'faith',
            'highlight'     => 1,
            'highlight_tag' => '**',
            'data_format'   => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringContainsString('**faith**', $results['kjv'][0]->text);
        $this->assertStringNotContainsString('<**>', $results['kjv'][0]->text);
        $this->assertStringNotContainsString('</**>', $results['kjv'][0]->text);
    }

    /**
     * The v3 default is read by key out of the config, and a typo in that key ('highlight_tag_v3 ')
     * resolved to NULL, which SqlSearch then backed up with the v2 element - every v3 response
     * came back highlighted with '<b>'. Pin the key rather than only the behaviour it drives.
     */
    public function testTheV3HighlightDefaultResolvesToAPlainTextMarker(): void
    {
        $default = config('bss.defaults.highlight_tag_v3');

        $this->assertNotNull($default, 'config("bss.defaults.highlight_tag_v3") did not resolve');
        $this->assertTrue(\App\Helpers::isPlainTextHighlightMarker($default), (string) $default);
    }

    /** v3 answers in Markdown, so its highlighting has to be Markdown too. */
    public function testTheV3EngineHighlightsWithMarkdown(): void
    {
        $Engine  = new EngineV3();
        $results = $Engine->actionQuery([
            'bible'       => 'kjv',
            'search'      => 'faith',
            'highlight'   => 1,
            'data_format' => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringContainsString('**faith**', $results['kjv'][0]->text);
        $this->assertStringNotContainsString('<b>', $results['kjv'][0]->text);
    }

    /** An HTML element name asked for on v3 is answered with Markdown all the same. */
    public function testTheV3EngineRefusesAnHtmlHighlightTag(): void
    {
        $Engine  = new EngineV3();
        $results = $Engine->actionQuery([
            'bible'         => 'kjv',
            'search'        => 'faith',
            'highlight'     => 1,
            'highlight_tag' => 'em',
            'data_format'   => 'raw',
        ]);

        $this->assertSame(3, $Engine->getErrorLevel()); // reported, not refused
        $this->assertStringContainsString('**faith**', $results['kjv'][0]->text);
        $this->assertStringNotContainsString('<em>', $results['kjv'][0]->text);
    }

    /**
     * A hyphen makes a legal custom element, so 'my-tag' used to be taken for a Markdown
     * marker and emitted on both sides of the match - 'the men are my-tagshepherdmy-tag'.
     * The word itself was corrupted, not merely left unhighlighted.
     */
    public function testACustomElementHighlightTagDoesNotRunIntoTheWord(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery([
            'bible'         => 'kjv',
            'search'        => 'faith',
            'highlight'     => 1,
            'highlight_tag' => 'my-tag',
            'data_format'   => 'raw',
        ]);

        $this->assertSame(3, $Engine->getErrorLevel()); // reported, not refused

        $text = $results['kjv'][0]->text;

        $this->assertStringNotContainsString('my-tag', $text);
        $this->assertStringContainsString('<b>faith</b>', $text);
    }

    /** The same tag on v3: not a plain-text marker, so the response stays Markdown. */
    public function testACustomElementHighlightTagIsMarkdownOnV3(): void
    {
        $Engine  = new EngineV3();
        $results = $Engine->actionQuery([
            'bible'         => 'kjv',
            'search'        => 'faith',
            'highlight'     => 1,
            'highlight_tag' => 'my-tag',
            'data_format'   => 'raw',
        ]);

        $this->assertSame(3, $Engine->getErrorLevel()); // reported, not refused

        $text = $results['kjv'][0]->text;

        $this->assertStringNotContainsString('my-tag', $text);
        $this->assertStringContainsString('**faith**', $text);
    }

    /**
     * A Markdown payload is not a marker. '![x](javascript:alert(1))' carries no character a
     * tag filter would catch, and emitting it on both sides of a match hands the client an
     * executable image the moment it renders the v3 response.
     *
     * @param string $tag
     */
    #[DataProvider('unsafeMarkerDataProvider')]
    public function testAnUnsafeMarkerIsNotEmittedIntoVerseText(string $tag): void
    {
        foreach([new EngineV2(), new EngineV3()] as $Engine) {
            $results = $Engine->actionQuery([
                'bible'         => 'kjv',
                'search'        => 'faith',
                'highlight'     => 1,
                'highlight_tag' => $tag,
                'data_format'   => 'raw',
            ]);

            $this->assertSame(3, $Engine->getErrorLevel(), $tag); // reported, not refused

            $text = $results['kjv'][0]->text;

            $this->assertStringNotContainsString($tag, $text, $tag . ' was emitted into verse text');
            $this->assertStringNotContainsStringIgnoringCase('javascript:', $text, $tag);
        }
    }

    public static function unsafeMarkerDataProvider(): array
    {
        return [
            'markdown image' => ['![x](javascript:alert(1))'],
            'markdown link'  => ['[x](javascript:alert(1))'],
            'pipes'          => ['||'],
        ];
    }

    /**
     * '%' is the delimiter SqlSearch::highlightResults() marks the end of a match with before
     * swapping it for the resolved tag, so a caller asking to highlight with it was writing
     * over the highlighter's own bookkeeping.
     */
    public function testTheHighlightersOwnDelimitersAreNotAcceptedAsMarkers(): void
    {
        foreach(['%', '&&'] as $tag) {
            $Engine  = new EngineV2();
            $results = $Engine->actionQuery([
                'bible'         => 'kjv',
                'search'        => 'faith',
                'highlight'     => 1,
                'highlight_tag' => $tag,
                'data_format'   => 'raw',
            ]);

            $this->assertSame(3, $Engine->getErrorLevel(), $tag); // reported, not refused

            $text = $results['kjv'][0]->text;

            $this->assertStringContainsString('<b>faith</b>', $text, $tag . ' did not fall back');
            $this->assertStringNotContainsString('%', $text, $tag);
            $this->assertStringNotContainsString('&&', $text, $tag);
        }
    }

    // -----------------------------------------------------------------------
    // highlight_tag - the whitelist applied at input validation
    // -----------------------------------------------------------------------

    /**
     * Every tag on a version's whitelist reaches the response as the caller asked for it.
     *
     * The check runs in Engine::_sanitizeInput(), so a value off the list is dropped before
     * the engine ever sees it and the request falls back to the configured default.
     *
     * @param string $version
     * @param string $tag
     */
    #[DataProvider('whitelistedTagDataProvider')]
    public function testAWhitelistedHighlightTagIsHonoured(string $class, string $tag): void
    {
        $Engine  = new $class();
        $results = $Engine->actionQuery([
            'bible'         => 'kjv',
            'search'        => 'faith',
            'highlight'     => 1,
            'highlight_tag' => $tag,
            'data_format'   => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors(), $tag);

        $text     = $results['kjv'][0]->text;
        $expected = ctype_alpha($tag) ? '<' . $tag . '>' : $tag;

        $this->assertStringContainsString($expected, $text, $class . ' / ' . $tag);
    }

    public static function whitelistedTagDataProvider(): array
    {
        $cases = [];

        foreach([EngineV2::class, EngineV3::class] as $class) {
            foreach($class::HIGHLIGHT_TAG_WHITELIST as $tag) {
                $cases[class_basename($class) . ' ' . $tag] = [$class, $tag];
            }
        }

        return $cases;
    }

    /**
     * A tag off the whitelist is dropped at validation and the configured default answers
     * instead - v2 wraps with its element, v3 with Markdown bold.
     *
     * The substitution is reported, not silent: see
     * testAnUnwhitelistedHighlightTagIsReportedAsANonFatalError().
     *
     * @param string $tag
     * @param string|null $forbidden What must not appear in the text, when the tag itself is
     *                               not already part of the fallback's own markup
     */
    #[DataProvider('unwhitelistedTagDataProvider')]
    public function testAnUnwhitelistedHighlightTagFallsBackToTheDefault(string $tag, ?string $forbidden): void
    {
        $v2 = new EngineV2();
        $r2 = $v2->actionQuery([
            'bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
            'highlight_tag' => $tag, 'data_format' => 'raw',
        ]);

        $this->assertStringContainsString('<b>faith</b>', $r2['kjv'][0]->text, 'v2 / ' . $tag);

        if($forbidden !== NULL) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $r2['kjv'][0]->text, 'v2 / ' . $tag);
        }

        $v3 = new EngineV3();
        $r3 = $v3->actionQuery([
            'bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
            'highlight_tag' => $tag, 'data_format' => 'raw',
        ]);

        $this->assertStringContainsString('**faith**', $r3['kjv'][0]->text, 'v3 / ' . $tag);
    }

    /**
     * The fallback is reported rather than silent.
     *
     * A rejected tag used to leave the response indistinguishable from one where the tag had
     * been honoured - same 200, same empty errors array, different markup - so an integrator
     * whose tag stopped being accepted had nothing to detect the change by.
     *
     * Level 3 (non-fatal): the results are still there and still highlighted, only with the
     * default tag.
     *
     * @param string $tag
     */
    #[DataProvider('unwhitelistedTagDataProvider')]
    public function testAnUnwhitelistedHighlightTagIsReportedAsANonFatalError(string $tag, ?string $forbidden): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery([
            'bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
            'highlight_tag' => $tag, 'data_format' => 'raw',
        ]);

        $this->assertTrue($Engine->hasErrors(), $tag);
        $this->assertSame(3, $Engine->getErrorLevel(), $tag);
        $this->assertNotEmpty($results['kjv'], 'results are still returned: ' . $tag);

        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors, $tag);
        $this->assertStringContainsString('Highlight tag', $errors[0], $tag);
    }

    /**
     * The tag the caller sent is named in the message, so the integrator can see which value
     * was refused without having to diff the markup.
     */
    public function testTheRejectedHighlightTagIsNamedInTheError(): void
    {
        $Engine = new EngineV2();
        $Engine->actionQuery([
            'bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
            'highlight_tag' => 'my-tag', 'data_format' => 'raw',
        ]);

        $this->assertStringContainsString("'my-tag'", $Engine->getErrors()[0]);
    }

    /**
     * The error is raised once per request, not once per rejected-value check.
     */
    public function testTheRejectedHighlightTagErrorIsNotRepeated(): void
    {
        $Engine = new EngineV2();
        $Engine->actionQuery([
            'bible' => 'kjv', 'search' => 'faith AND hope', 'highlight' => 1,
            'highlight_tag' => 'my-tag', 'data_format' => 'raw',
        ]);

        $this->assertCount(1, $Engine->getErrors());
    }

    /**
     * A tag on the whitelist, and an absent tag, are both answered without an error - the
     * report is for the substitution, not for asking to highlight at all.
     */
    public function testAnAcceptedOrAbsentHighlightTagRaisesNoError(): void
    {
        $accepted = new EngineV2();
        $accepted->actionQuery([
            'bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
            'highlight_tag' => 'em', 'data_format' => 'raw',
        ]);

        $this->assertFalse($accepted->hasErrors());

        $absent = new EngineV2();
        $absent->actionQuery([
            'bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
            'data_format' => 'raw',
        ]);

        $this->assertFalse($absent->hasErrors());
    }

    /**
     * highlight_tag is the only field the report is wired to. 'markup' is whitelisted for the
     * same reason but falls back deliberately in silence - an unrecognised mode resolves to
     * the least permissive one, which is not a substitution the caller needs to act on.
     */
    public function testAnUnwhitelistedMarkupModeStillFallsBackInSilence(): void
    {
        $Engine = new EngineV2();
        $Engine->actionQuery([
            'bible' => 'kjv', 'search' => 'faith', 'markup' => 'not-a-mode',
            'data_format' => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());
    }

    public static function unwhitelistedTagDataProvider(): array
    {
        return [
            'script'          => ['script',  'script'],
            'iframe'          => ['iframe',  'iframe'],
            'img'             => ['img',     'img'],
            'heading'         => ['h1',      'h1'],
            'custom element'  => ['my-tag',  'my-tag'],
            // '<b>' is the default tag written out in full, so the fallback's own markup
            // contains it - there is nothing to forbid beyond the doubling checked elsewhere.
            'angle bracketed' => ['<b>',     NULL],
            'search wildcard' => ['%',       '%'],
            'search alias'    => ['&&',      '&&'],
            'markdown image'  => ['![x](javascript:alert(1))', 'javascript:'],
        ];
    }

    /**
     * An element name is on v2's whitelist and not on v3's, so the same request is answered
     * with HTML by one version and with Markdown by the other.
     */
    public function testAnElementNameIsAcceptedOnV2AndRefusedOnV3(): void
    {
        $this->assertContains('em', EngineV2::HIGHLIGHT_TAG_WHITELIST);
        $this->assertNotContains('em', EngineV3::HIGHLIGHT_TAG_WHITELIST);

        $v2 = new EngineV2();
        $r2 = $v2->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
                                'highlight_tag' => 'em', 'data_format' => 'raw']);

        $v3 = new EngineV3();
        $r3 = $v3->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
                                'highlight_tag' => 'em', 'data_format' => 'raw']);

        $this->assertStringContainsString('<em>faith</em>', $r2['kjv'][0]->text);
        $this->assertStringNotContainsString('<em>', $r3['kjv'][0]->text);
        $this->assertStringContainsString('**faith**', $r3['kjv'][0]->text);

        // And the version that refuses it says so, so a v2 client moving to v3 is not left
        // reading the markup to find out.
        $this->assertFalse($v2->hasErrors());
        $this->assertTrue($v3->hasErrors());
        $this->assertSame(3, $v3->getErrorLevel());
    }

    /** The whitelist is case-insensitive and the caller's own casing is kept. */
    public function testTheWhitelistIsCaseInsensitive(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
                                         'highlight_tag' => 'EM', 'data_format' => 'raw']);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringContainsString('<EM>faith</EM>', $results['kjv'][0]->text);
    }

    /** A Markdown marker other than the default is the caller's choice and is honoured. */
    public function testTheV3EngineHonoursAnExplicitMarkdownTag(): void
    {
        $Engine  = new EngineV3();
        $results = $Engine->actionQuery([
            'bible'         => 'kjv',
            'search'        => 'faith',
            'highlight'     => 1,
            'highlight_tag' => '__',
            'data_format'   => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringContainsString('__faith__', $results['kjv'][0]->text);
    }

    /**
     * BSS-290: an install's own DEFAULT_HIGHLIGHT_TAG is honoured even when the element
     * whitelist does not name it.
     *
     * The whitelist is there for the caller-supplied tag, which reaches verse text unescaped.
     * Applying it to the configured default too rewrote 'high' - the element this repo's own
     * UnicodeTest used to send - to 'b', so an install styling a custom element lost every
     * highlight with nothing in the response or the log to say why.
     */
    public function testAConfiguredDefaultOffTheWhitelistIsHonouredOnV2(): void
    {
        config(['bss.defaults.highlight_tag' => 'high']);

        $Engine  = new EngineV2();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
                                         'data_format' => 'raw']);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringContainsString('<high>faith</high>', $results['kjv'][0]->text);
    }

    /** And a caller's rejected tag falls back to that same configured element, not to 'b'. */
    public function testARejectedTagFallsBackToTheConfiguredDefaultOnV2(): void
    {
        config(['bss.defaults.highlight_tag' => 'high']);

        $Engine  = new EngineV2();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
                                         'highlight_tag' => 'script', 'data_format' => 'raw']);

        $this->assertSame(3, $Engine->getErrorLevel()); // reported, not refused
        $this->assertStringContainsString('<high>faith</high>', $results['kjv'][0]->text);
        $this->assertStringNotContainsString('script', $results['kjv'][0]->text);
    }

    /**
     * v2 only. The v2 default is an element name and v3 answers in Markdown, so the same
     * configuration must not put '<high>' into a v3 response - EngineV3 reads its own
     * highlight_tag_v3 and the marker branch of buildHighlightTags() returns before the v2
     * default is ever consulted.
     */
    public function testTheConfiguredV2DefaultDoesNotReachAV3Response(): void
    {
        config(['bss.defaults.highlight_tag' => 'high']);

        $Engine  = new EngineV3();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'highlight' => 1,
                                         'data_format' => 'raw']);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringContainsString('**faith**', $results['kjv'][0]->text);
        $this->assertStringNotContainsString('<high>', $results['kjv'][0]->text);
    }

    // -----------------------------------------------------------------------
    // Context highlighting
    // -----------------------------------------------------------------------

    /**
     * Passage context highlighting wraps the requested verse rather than a keyword, but it
     * resolves the tag the same way - the two must not disagree about what '**' means.
     */
    public function testContextHighlightingHonoursAMarkdownTag(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery([
            'bible'         => 'kjv',
            'request'       => 'John 3:16',
            'context'       => 1,
            'highlight'     => 1,
            'highlight_tag' => '**',
            'data_format'   => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());

        $verse = $this->findVerse($results['kjv'], 16);

        $this->assertStringStartsWith('**For God so loved the world', $verse->text);
        $this->assertStringEndsWith('**', $verse->text);
    }

    public function testContextHighlightingIsHtmlOnV2ByDefault(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery([
            'bible'       => 'kjv',
            'request'     => 'John 3:16',
            'context'     => 1,
            'highlight'   => 1,
            'data_format' => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());

        $tag   = config('bss.defaults.highlight_tag');
        $verse = $this->findVerse($results['kjv'], 16);

        $this->assertStringStartsWith('<' . $tag . '>', $verse->text);
        $this->assertStringEndsWith('</' . $tag . '>', $verse->text);
    }

    public function testContextHighlightingIsMarkdownOnV3(): void
    {
        $Engine  = new EngineV3();
        $results = $Engine->actionQuery([
            'bible'       => 'kjv',
            'request'     => 'John 3:16',
            'context'     => 1,
            'highlight'   => 1,
            'data_format' => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());

        $verse = $this->findVerse($results['kjv'], 16);

        $this->assertStringStartsWith('**', $verse->text);
        $this->assertStringEndsWith('**', $verse->text);
        $this->assertStringNotContainsString('<b>', $verse->text);
    }

    // -----------------------------------------------------------------------
    // actionStatics - research_desc
    // -----------------------------------------------------------------------

    /**
     * 'research_desc' is operator-editable soft config, so it is HTML the same way an
     * imported description is, and goes through the same hook: whitelisted HTML on v2,
     * Markdown on v3.
     *
     * config() is set on the test's own copy, so nothing installed is touched.
     */
    public function testTheResearchDescriptionIsSanitizedPerVersion(): void
    {
        config(['bss.research_description' => 'Research only. <a href="https://example.com">details</a>']);

        $v2 = (new EngineV2())->actionStatics([]);
        $v3 = (new EngineV3())->actionStatics([]);

        $this->assertStringContainsString('<a href="https://example.com">details</a>', $v2->research_desc);

        $this->assertContainsNoHtmlTags($v3->research_desc, 'v3 statics carried HTML');
        $this->assertStringContainsString('[details](https://example.com)', $v3->research_desc);
    }

    /**
     * And what the purifier refuses is gone on both, rather than reaching the response as
     * markup the operator did not intend to publish.
     */
    public function testTheResearchDescriptionCannotCarryAScript(): void
    {
        config(['bss.research_description' => 'Research only.<script>alert(1)</script>']);

        foreach([new EngineV2(), new EngineV3()] as $Engine) {
            $desc = (string) $Engine->actionStatics([])->research_desc;

            $this->assertStringNotContainsStringIgnoringCase('<script', $desc, get_class($Engine));
            $this->assertStringNotContainsStringIgnoringCase('alert(1)', $desc, get_class($Engine));
        }
    }

    // -----------------------------------------------------------------------
    // actionStrongs
    // -----------------------------------------------------------------------

    /**
     * Strong's entries are the other imported HTML in the API. The italics that carry the
     * lexical sense are on the whitelist and must survive; the count prefix must not.
     */
    public function testStrongsEntriesKeepTheirItalicsAndLoseTheCount(): void
    {
        $Engine = new EngineV2();
        $results = $Engine->actionStrongs(['strongs' => 'H1234']);

        $this->assertFalse($Engine->hasErrors());
        $this->assertSame(1234, $results[0]['id']);
        $this->assertStringContainsString('<i>cleave</i>', $results[0]['entry']);
        $this->assertStringNotContainsString('Count:', (string) $results[0]['tvm']);
    }

    /**
     * Strong's definitions go through the engine's hook like every other imported field, so
     * v3 answers with Markdown rather than the italics v2 keeps.
     */
    public function testStrongsEntriesAreMarkdownOnV3(): void
    {
        $Engine = new EngineV3();
        $results = $Engine->actionStrongs(['strongs' => 'H1234']);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringContainsString('*cleave*', $results[0]['entry']);
        $this->assertContainsNoHtmlTags($results[0]['entry']);
    }

    /**
     * The great majority of Strong's definitions have no 'tvm' - 14,248 of the 14,696 rows -
     * and _formatStrongs() sets the field to NULL before handing it to the sanitizer. A
     * non-nullable sanitizer raised a TypeError there, which took out /api/strongs for very
     * nearly every lookup; answering '' instead would silently change the shape a client
     * branching on '=== null' has always seen.
     */
    public function testStrongsEntriesWithoutATvmAreReturned(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionStrongs(['strongs' => 'H1']);

        $this->assertFalse($Engine->hasErrors());
        $this->assertSame(1, $results[0]['id']);
        $this->assertNull($results[0]['tvm']);
        $this->assertStringContainsString('<i>father</i>', $results[0]['entry']);
    }

    public function testStrongsEntriesWithoutATvmAreReturnedOnV3(): void
    {
        $Engine  = new EngineV3();
        $results = $Engine->actionStrongs(['strongs' => 'H1']);

        $this->assertFalse($Engine->hasErrors());
        $this->assertNull($results[0]['tvm']);
        $this->assertStringContainsString('*father*', $results[0]['entry']);
    }

    /** The NULL columns come back as JSON null, not as empty strings. */
    public function testAStrongsEntryWithoutATvmSerializesItAsNull(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionStrongs(['strongs' => 'H1']);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringContainsString('"tvm":null', json_encode($results[0]));
    }

    // -----------------------------------------------------------------------
    // Nullable fields
    // -----------------------------------------------------------------------

    /**
     * The listing selects 'description' unconditionally and sanitizes it, and
     * getCopyrightStatement() falls back to that same column, so a NULL there took out the
     * whole Bible listing - the response every client opens with.
     *
     * A missing field stays null. Helpers::sanitizeHtml() answers NULL with '' and always
     * has, but the engine hooks hold the distinction so the listing reports an absent
     * description the way it always did.
     *
     * Conditional on the install: no enabled Bible here has a NULL description, so this
     * ordinarily has nothing to check and the hooks' own NULL contract is what carries the
     * coverage - see testTheEditorSanitizeHookAnswersNullWithNullOnBothEngines() in
     * tests/Unit/Engines/EngineVersionTest.php.
     */
    public function testBiblesWithoutADescriptionAreListed(): void
    {
        $missing = \App\Models\Bible::whereNull('description')->where('enabled', 1)->pluck('module')->all();

        if(empty($missing)) {
            $this->assertTrue(true, 'Every installed Bible has a description');
            return;
        }

        foreach([new EngineV2(), new EngineV3()] as $Engine) {
            $bibles = $Engine->actionBibles([]);

            foreach($missing as $module) {
                // Re-checked after the listing was built, not before: in parallel mode another
                // test class shares this database, and tests/Feature/Controllers/BibleControllerTest
                // imports an enabled Bible with no description and removes it again. A fixture
                // that has since gone is correctly absent from the listing, and reporting it
                // as missing failed this test for a reason that has nothing to do with it.
                if(!\App\Models\Bible::whereNull('description')->where('module', $module)->where('enabled', 1)->exists()) {
                    $this->assertTrue(true, 'This Bible now has a description');
                    continue;
                }

                $this->assertArrayHasKey($module, $bibles, $module . ' is missing from the listing');
                $this->assertNull($bibles[$module]['description'], $module);
            }
        }
    }

    // -----------------------------------------------------------------------
    // actionBibles - the generated copyright statement
    // -----------------------------------------------------------------------

    /**
     * When bibles.copyright_statement is empty the statement is built by
     * Copyright::getProcessedCopyrightStatement() instead, and no accessor purifies that -
     * Bible::copyrightStatement() only guards the column. Bible::getCopyrightStatement()
     * purifies all three of its branches so every consumer inherits it, the API and a
     * rendered file alike.
     *
     * The escaping that keeps the copyright row's URL inside its href is pinned in
     * tests/Unit/Models/CopyrightStatementTest.php.
     */
    public function testTheGeneratedCopyrightStatementIsPurifiedByTheModel(): void
    {
        $generated = \App\Models\Bible::where('enabled', 1)
            ->whereNotNull('copyright_id')
            ->where(function($Query) {
                $Query->whereNull('copyright_statement')->orWhere('copyright_statement', '');
            })
            ->get();

        if($generated->isEmpty()) {
            $this->markTestSkipped('Every enabled Bible carries its own copyright statement');
        }

        foreach($generated as $Bible) {
            $statement = (string) $Bible->getCopyrightStatement();

            // Purified already, before any engine sees it: running the same allowlist over it
            // again changes nothing.
            $this->assertSame(
                $statement,
                \App\Helpers::sanitizeEditorHtml($statement),
                $Bible->module . ': the generated statement was not purified by the model'
            );
        }
    }

    /**
     * The whole listing, both versions: nothing in a copyright statement carries an event
     * handler or a javascript scheme, whichever branch of getCopyrightStatement() built it.
     */
    public function testNoCopyrightStatementCarriesAnExecutableAttribute(): void
    {
        foreach([new EngineV2(), new EngineV3()] as $Engine) {
            $bibles = $Engine->actionBibles([]);

            foreach($bibles as $module => $bible) {
                $statement = (string) ($bible['copyright_statement'] ?? '');

                $this->assertDoesNotMatchRegularExpression('/\bon[a-z]+\s*=/i', $statement, $module);
                $this->assertStringNotContainsStringIgnoringCase('javascript:', $statement, $module);
            }
        }
    }

    /** Locates a verse by number within one Bible's results. */
    private function findVerse(array $verses, int $verse): \stdClass
    {
        foreach($verses as $Verse) {
            if($Verse->verse == $verse) {
                return $Verse;
            }
        }

        $this->fail('Verse ' . $verse . ' is missing from the results');
    }
}
