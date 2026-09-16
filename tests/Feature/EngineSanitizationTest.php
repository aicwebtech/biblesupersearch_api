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

    // -----------------------------------------------------------------------
    // actionQuery - verse text
    // -----------------------------------------------------------------------

    /**
     * The sanitizer was moved inside the per-verse loop so it also runs for markup=raw. Raw
     * must still hand back the Bible's own markup - the bracketed added words the renderers
     * turn into italics - or every raw consumer silently loses it.
     */
    public function testRawMarkupSurvivesTheSanitizer(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery([
            'bible'       => 'kjv',
            'request'     => 'Genesis 1:2',
            'markup'      => 'raw',
            'data_format' => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringContainsString('[was]', $results['kjv'][0]->text);
    }

    /** The default markup mode still strips that same markup. */
    public function testDefaultMarkupModeStillStripsBibleMarkup(): void
    {
        $Engine  = new EngineV2();
        $results = $Engine->actionQuery([
            'bible'       => 'kjv',
            'request'     => 'Genesis 1:2',
            'data_format' => 'raw',
        ]);

        $this->assertFalse($Engine->hasErrors());
        $this->assertStringNotContainsString('[was]', $results['kjv'][0]->text);
        $this->assertStringContainsString('was upon the face of the deep', $results['kjv'][0]->text);
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
     * highlight_tag is caller-supplied and is applied after _processMarkup() has already run
     * the sanitizer, so an unrestricted tag name would write an executable element straight
     * into verse text. Helpers::buildHighlightTags() whitelists the name instead.
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

            $this->assertFalse($Engine->hasErrors(), $tag);

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

        $this->assertFalse($Engine->hasErrors());
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

        $this->assertFalse($Engine->hasErrors());
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

        $this->assertFalse($Engine->hasErrors());

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

        $this->assertFalse($Engine->hasErrors());

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

            $this->assertFalse($Engine->hasErrors(), $tag);

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

            $this->assertFalse($Engine->hasErrors(), $tag);

            $text = $results['kjv'][0]->text;

            $this->assertStringContainsString('<b>faith</b>', $text, $tag . ' did not fall back');
            $this->assertStringNotContainsString('%', $text, $tag);
            $this->assertStringNotContainsString('&&', $text, $tag);
        }
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
     * Several installed Bibles have no description at all, and getCopyrightStatement() falls
     * back to that same column. The listing selects it unconditionally and sanitizes it, so a
     * NULL there took out the whole Bible listing - the response every client opens with.
     *
     * A missing field stays null. Helpers::sanitizeHtml() answers NULL with '' and always
     * has, but the engine hooks hold the distinction so the listing reports an absent
     * description the way it always did.
     */
    public function testBiblesWithoutADescriptionAreListed(): void
    {
        $missing = \App\Models\Bible::whereNull('description')->where('enabled', 1)->pluck('module')->all();

        if(empty($missing)) {
            $this->markTestSkipped('Every installed Bible has a description');
        }

        foreach([new EngineV2(), new EngineV3()] as $Engine) {
            $bibles = $Engine->actionBibles([]);

            foreach($missing as $module) {
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
