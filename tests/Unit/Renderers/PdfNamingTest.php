<?php

namespace Tests\Unit\Renderers;

use PHPUnit\Framework\TestCase;
use App\Renderers\PdfCompact;
use App\Renderers\PdfCompactA4;
use App\Renderers\PdfCompactUl;
use App\Renderers\PdfCompactUlA4;
use App\Renderers\PdfNormal;

/**
 * The four PDF download options are one renderer differing only in page size and how the
 * words of Christ are marked. Their labels are generated rather than written out, so
 * getName() is the only thing distinguishing them in the download picker - two variants
 * producing the same label would be indistinguishable to the user.
 *
 * Pure static logic; no PDF is produced here.
 */
class PdfNamingTest extends TestCase
{
    public function testLetterCompactIsLabelledWithItsFormatAndRedLetters(): void
    {
        $this->assertSame('Compact Text, Letter, Words of Christ in <span class="red">Red</span>', PdfCompact::getName());
    }

    public function testA4CompactIsLabelledWithItsFormat(): void
    {
        $this->assertStringContainsString('A4', PdfCompactA4::getName());
        $this->assertStringNotContainsString('Letter', PdfCompactA4::getName());
    }

    /**
     * The underlined variants exist for monochrome printing, so their label has to say
     * underlined rather than red.
     */
    public function testUnderlinedVariantsSayUnderlined(): void
    {
        $this->assertStringContainsString('<u>Underlined</u>', PdfCompactUl::getName());
        $this->assertStringNotContainsString('Red', PdfCompactUl::getName());
    }

    public function testUnderlinedA4CombinesBothDistinctions(): void
    {
        $name = PdfCompactUlA4::getName();

        $this->assertStringContainsString('A4', $name);
        $this->assertStringContainsString('<u>Underlined</u>', $name);
    }

    /**
     * Every offered PDF variant must be distinguishable by its label alone.
     */
    public function testEveryPdfVariantHasADistinctLabel(): void
    {
        $names = [
            PdfCompact::getName(),
            PdfCompactA4::getName(),
            PdfCompactUl::getName(),
            PdfCompactUlA4::getName(),
        ];

        $this->assertSame($names, array_unique($names));
    }

    /**
     * The LETTER page format is title-cased for display; the others are shown as declared.
     */
    public function testTheLetterFormatIsTitleCasedForDisplay(): void
    {
        $this->assertStringContainsString('Letter', PdfCompact::getName());
        $this->assertStringNotContainsString('LETTER', PdfCompact::getName());
    }

    /**
     * A renderer that opts out of format-inclusive naming keeps its plain name - this is the
     * default, so a new PDF variant does not accidentally advertise a format.
     */
    public function testARendererOptingOutKeepsItsPlainName(): void
    {
        $this->assertSame('Normal Text', PdfNormal::getName());
    }

    // -----------------------------------------------------------------------
    // Descriptions
    // -----------------------------------------------------------------------

    /**
     * The underlined variants are the ones that print legibly without colour, and say so.
     */
    public function testUnderlinedVariantsAdvertiseMonochromeSupport(): void
    {
        $this->assertStringContainsString('Monochrome friendly.', PdfCompactUl::getDescription());
        $this->assertStringContainsString('Monochrome friendly.', PdfCompactUlA4::getDescription());
    }

    public function testRedLetterVariantsDoNotAdvertiseMonochromeSupport(): void
    {
        $this->assertStringNotContainsString('Monochrome', PdfCompact::getDescription());
        $this->assertStringNotContainsString('Monochrome', PdfCompactA4::getDescription());
    }
}
