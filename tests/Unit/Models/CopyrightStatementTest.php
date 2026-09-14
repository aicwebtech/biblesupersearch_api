<?php

namespace Tests\Unit\Models;

use App\Helpers;
use App\Models\Bible;
use App\Models\Copyright;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * BSS-290: Copyright::getProcessedCopyrightStatement() builds the statement shown for a Bible
 * that carries none of its own - 32 of the enabled Bibles here - by interpolating the
 * copyright row's own URL into an href.
 *
 * Nothing purified that on the way out: Bible::copyrightStatement() only guards the column,
 * and the generated statement never goes through it. So the URL is escaped where it is built
 * and Engine::_sanitizeHtml() purifies the result, and both halves are pinned here.
 *
 * Plain PHP objects: attributes are set with setRawAttributes(), no database.
 */
class CopyrightStatementTest extends TestCase
{
    /** A copyright row carrying $attributes as its raw (database-side) values. */
    private function copyright(array $attributes): Copyright
    {
        $Copyright = new Copyright();
        $Copyright->setRawAttributes($attributes);

        return $Copyright;
    }

    /**
     * Asserts the statement parses to exactly one link and nothing else executable - no
     * element the template did not write, and no attribute on the link beyond its own two.
     *
     * Structural rather than textual: an escaped payload still reads as 'onerror=...' in the
     * source, and what matters is that the parser sees it as attribute text and not as
     * markup.
     */
    private function assertStatementHasNothingButTheIntendedLink(string $statement): void
    {
        $Document = new \DOMDocument();

        $loaded = $Document->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $statement . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        $this->assertTrue($loaded, 'The statement could not be parsed');

        foreach(['script', 'img', 'iframe', 'object', 'svg'] as $element) {
            $this->assertSame(0, $Document->getElementsByTagName($element)->length, $element . ' was injected');
        }

        foreach($Document->getElementsByTagName('a') as $Anchor) {
            foreach($Anchor->attributes as $Attribute) {
                $this->assertContains(
                    $Attribute->nodeName,
                    ['href', 'target'],
                    'The link carries an attribute the template never wrote: ' . $Attribute->nodeName
                );
            }
        }
    }

    /**
     * A URL is admin-supplied and lands inside a single-quoted href, so an apostrophe in it
     * closes the attribute and everything after it is read as markup.
     */
    #[DataProvider('hostileUrlDataProvider')]
    public function testAHostileUrlCannotBreakOutOfTheHref(string $url): void
    {
        $Copyright = $this->copyright([
            'type'                        => 'public_domain',
            'url'                         => $url,
            'default_copyright_statement' => 'Public domain.',
        ]);

        $this->assertStatementHasNothingButTheIntendedLink($Copyright->getProcessedCopyrightStatement());
    }

    /** The creative-commons branch builds its own link and needs the same guard. */
    #[DataProvider('hostileUrlDataProvider')]
    public function testAHostileUrlCannotBreakOutOfTheCreativeCommonsHref(string $url): void
    {
        $Copyright = $this->copyright([
            'type' => 'creative_commons',
            'name' => 'CC BY-SA 4.0',
            'url'  => $url,
        ]);

        $this->assertStatementHasNothingButTheIntendedLink($Copyright->getProcessedCopyrightStatement());
    }

    /** Left unescaped, the apostrophe in the URL closed the attribute - it is an entity now. */
    public function testTheApostropheInAHostileUrlIsEscaped(): void
    {
        $Copyright = $this->copyright([
            'type'                        => 'public_domain',
            'url'                         => "x' onmouseover='alert(1)",
            'default_copyright_statement' => 'Public domain.',
        ]);

        $statement = $Copyright->getProcessedCopyrightStatement();

        $this->assertStringContainsString('&#039;', $statement);
        $this->assertStringNotContainsString("x' onmouseover", $statement);
    }

    public static function hostileUrlDataProvider(): array
    {
        return [
            'attribute break out' => ["x' onmouseover='alert(1)"],
            'tag break out'       => ["x'><script>alert(1)</script>"],
            'image handler'       => ["x'><img src=x onerror=alert(1)>"],
        ];
    }

    /**
     * Escaping the URL is not the whole guard - default_copyright_statement is admin-supplied
     * too and is concatenated in as it stands. The purifier is what covers that, and what
     * refuses a 'javascript:' scheme the escaping leaves intact.
     */
    public function testThePurifierClearsWhatEscapingTheUrlDoesNot(): void
    {
        $Copyright = $this->copyright([
            'type'                        => 'public_domain',
            'url'                         => 'javascript:alert(1)',
            'default_copyright_statement' => 'Public domain. <script>alert(1)</script><img src=x onerror=alert(1)>',
        ]);

        $sanitized = Helpers::sanitizeHtml($Copyright->getProcessedCopyrightStatement());

        $this->assertStringContainsString('Public domain.', $sanitized);
        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('onerror', $sanitized);
        $this->assertStringNotContainsString('javascript:', $sanitized);
    }

    /** An ordinary URL is unchanged by the escaping and survives the purifier as a link. */
    public function testAnOrdinaryUrlStillBecomesALink(): void
    {
        $Copyright = $this->copyright([
            'type'                        => 'public_domain',
            'url'                         => 'https://example.com/license',
            'default_copyright_statement' => 'Used by permission.',
        ]);

        $sanitized = Helpers::sanitizeHtml($Copyright->getProcessedCopyrightStatement());

        $this->assertStringContainsString('Used by permission.', $sanitized);
        $this->assertStringContainsString('https://example.com/license', $sanitized);
        $this->assertStringContainsString('<a href=', $sanitized);
    }

    /** An '&' in a query string is escaped into an entity, which is what an href wants. */
    public function testAnAmpersandInTheUrlIsEscaped(): void
    {
        $Copyright = $this->copyright([
            'type'                        => 'public_domain',
            'url'                         => 'https://example.com/l?a=1&b=2',
            'default_copyright_statement' => 'Used by permission.',
        ]);

        $this->assertStringContainsString('a=1&amp;b=2', $Copyright->getProcessedCopyrightStatement());
    }

    /** The year-and-owner prefix is unaffected by the escaping. */
    public function testTheCreativeCommonsStatementStillCarriesTheYearAndOwner(): void
    {
        $Copyright = $this->copyright([
            'type' => 'creative_commons',
            'name' => 'CC BY-SA 4.0',
            'url'  => 'https://example.com/by-sa',
        ]);

        $Bible = new Bible();
        $Bible->setRawAttributes(['year' => 2011, 'owner' => 'Example Trust']);

        $statement = $Copyright->getProcessedCopyrightStatement($Bible);

        $this->assertStringContainsString('2011', $statement);
        $this->assertStringContainsString('Example Trust', $statement);
        $this->assertStringContainsString('CC BY-SA 4.0', $statement);
    }
}
