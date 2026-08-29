<?php

namespace Tests\Unit\Renderers;

use PHPUnit\Framework\TestCase;
use App\Renderers\TCPDFBible;

/**
 * TCPDFBible extends TCPDF to track which book and chapter the renderer is currently on, so
 * the running header and the table of contents can label each page.
 *
 * TCPDF needs no Laravel application, so this runs as a unit test. Nothing here produces a
 * PDF; the page-drawing methods need document state and are exercised through the PDF
 * renderers instead.
 */
class TCPDFBibleTest extends TestCase
{
    private function verse(string $bookName, int $chapter, int $verse): object
    {
        return (object) [
            'book_name' => $bookName,
            'chapter'   => $chapter,
            'verse'     => $verse,
        ];
    }

    public function testPageCountStartsAtZero(): void
    {
        $this->assertSame(0, (new TCPDFBible())->getBiblePageCount());
    }

    public function testBoldIsAllowedByDefault(): void
    {
        $this->assertTrue((new TCPDFBible())->allow_bold);
    }

    public function testSettingAVerseRecordsItsBookChapterAndVerse(): void
    {
        $pdf = new TCPDFBible();
        $pdf->setCurrentVerse($this->verse('Genesis', 1, 1));

        $this->assertSame('Genesis', $pdf->current_book);
        $this->assertSame(1, $pdf->current_chapter);
        $this->assertSame(1, $pdf->current_verse);
    }

    /**
     * The first verse seen also fixes the starting book and chapter, which the header uses to
     * label a page that begins mid-book.
     */
    public function testTheFirstVerseFixesTheStartingBookAndChapter(): void
    {
        $pdf = new TCPDFBible();
        $pdf->setCurrentVerse($this->verse('Genesis', 1, 1));
        $pdf->setCurrentVerse($this->verse('Exodus', 4, 7));

        $start = new \ReflectionProperty(TCPDFBible::class, 'start_book');

        $this->assertSame('Genesis', $start->getValue($pdf), 'the start book must not move with the cursor');
    }

    /**
     * The previous position is what lets the renderer notice a book or chapter boundary.
     */
    public function testThePreviousPositionTrailsTheCurrentOne(): void
    {
        $pdf = new TCPDFBible();
        $pdf->setCurrentVerse($this->verse('Genesis', 1, 1));
        $pdf->setCurrentVerse($this->verse('Genesis', 2, 1));

        $prevBook    = new \ReflectionProperty(TCPDFBible::class, 'prev_book');
        $prevChapter = new \ReflectionProperty(TCPDFBible::class, 'prev_chapter');

        $this->assertSame('Genesis', $prevBook->getValue($pdf));
        $this->assertSame(1, $prevChapter->getValue($pdf));
        $this->assertSame(2, $pdf->current_chapter);
    }

    /**
     * Passing no verse resets the whole cursor, which is how the renderer starts a new Bible
     * without carrying the previous one's position into its headers.
     */
    public function testPassingNoVerseClearsTheEntireCursor(): void
    {
        $pdf = new TCPDFBible();
        $pdf->setCurrentVerse($this->verse('Genesis', 1, 1));
        $pdf->setCurrentVerse($this->verse('Genesis', 2, 1));

        $pdf->setCurrentVerse(null);

        $this->assertNull($pdf->current_book);
        $this->assertNull($pdf->current_chapter);
        $this->assertNull($pdf->current_verse);

        foreach (['start_book', 'start_chapter', 'prev_book', 'prev_chapter'] as $name) {
            $property = new \ReflectionProperty(TCPDFBible::class, $name);

            $this->assertNull($property->getValue($pdf), "{$name} should be cleared");
        }
    }

    /**
     * After a reset the next verse becomes the new starting point.
     */
    public function testTheStartingPointIsReestablishedAfterAReset(): void
    {
        $pdf = new TCPDFBible();
        $pdf->setCurrentVerse($this->verse('Genesis', 1, 1));
        $pdf->setCurrentVerse(null);
        $pdf->setCurrentVerse($this->verse('Matthew', 5, 3));

        $start = new \ReflectionProperty(TCPDFBible::class, 'start_book');

        $this->assertSame('Matthew', $start->getValue($pdf));
    }

    // -----------------------------------------------------------------------
    // Bookmark titles
    // -----------------------------------------------------------------------

    /**
     * Bookmark titles come from rendered HTML, but a PDF outline entry is plain text - tags
     * would otherwise appear literally in the reader's sidebar.
     */
    public function testTitleFormattingStripsMarkup(): void
    {
        $pdf = new TCPDFBible();

        $this->assertSame('Genesis 1', $pdf->formatTitle('<b>Genesis 1</b>'));
    }

    public function testTitleFormattingTurnsBlockTagsIntoNewlines(): void
    {
        $pdf = new TCPDFBible();

        $this->assertSame("Genesis\n1", $pdf->formatTitle('<p>Genesis</p>1'));
    }

    public function testTitleFormattingCollapsesRepeatedNewlines(): void
    {
        $pdf = new TCPDFBible();

        $this->assertSame("Genesis\n1", $pdf->formatTitle("<p>Genesis</p>\n\n\n1"));
    }

    public function testTitleFormattingRemovesCarriageReturns(): void
    {
        $pdf = new TCPDFBible();

        $this->assertStringNotContainsString("\r", $pdf->formatTitle("Genesis\r\n1"));
    }

    public function testTitleFormattingLeavesPlainTextAlone(): void
    {
        $pdf = new TCPDFBible();

        $this->assertSame('Genesis 1', $pdf->formatTitle('Genesis 1'));
    }
}
