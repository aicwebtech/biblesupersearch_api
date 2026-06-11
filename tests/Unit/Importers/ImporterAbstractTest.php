<?php

namespace Tests\Unit\Importers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Importers\ImporterAbstract;
use Illuminate\Http\UploadedFile;

class ImporterAbstractTest extends TestCase
{
    /** Returns a concrete anonymous subclass for testing protected methods. */
    private function makeImporter(): ImporterAbstract
    {
        return new class extends ImporterAbstract {
            protected function _importHelper(\App\Models\Bible &$Bible): bool { return true; }
            public function checkUploadedFile(UploadedFile $File): bool { return true; }

            public function callPostFormatText(string $text): string
            {
                return $this->_postFormatText($text);
            }

            public function callReplaceTagsIfNeeded(array $find, array $rep, string $text): string
            {
                return $this->_replaceTagsIfNeeded($find, $rep, $text);
            }
        };
    }

    // -----------------------------------------------------------------------
    // sanitizeFileName
    // -----------------------------------------------------------------------

    #[DataProvider('sanitizeFileNameDataProvider')]
    public function testSanitizeFileName(string $input, string $expected): void
    {
        $this->assertSame($expected, ImporterAbstract::sanitizeFileName($input));
    }

    public static function sanitizeFileNameDataProvider(): array
    {
        return [
            'plain name'               => ['kjv.zip',         'kjv.zip'],
            'leading/trailing spaces'  => [' kjv.zip ',       'kjv.zip'],
            'path separators removed'  => ['../../etc/passwd', 'etcpasswd'],
            'null bytes removed'       => ["kjv\0.zip",        'kjv.zip'],
            'dots preserved once'      => ['my.bible.zip',     'my.bible.zip'],
            'double dots collapsed'    => ['my..bible.zip',    'mybible.zip'],
            'allowed brackets'         => ['[kjv](en).zip',   '[kjv](en).zip'],
            'asterisks removed'        => ['kjv*.zip',        'kjv.zip'],
        ];
    }

    // -----------------------------------------------------------------------
    // bible_attributes setters / getters
    // -----------------------------------------------------------------------

    public function testResetBibleAttributesSetsExpectedDefaults(): void
    {
        $imp = $this->makeImporter();
        $imp->setBibleAttributes(['name' => 'Test', 'module' => 'test_mod']);
        $imp->resetBibleAttributes();

        $attrs = $imp->getBibleAttributes();
        $this->assertIsArray($attrs);
        $this->assertArrayHasKey('name', $attrs);
        $this->assertNull($attrs['name']);
        $this->assertNull($attrs['module']);
    }

    public function testSetAndGetBibleAttributesRoundTrip(): void
    {
        $imp  = $this->makeImporter();
        $data = ['name' => 'King James', 'module' => 'kjv', 'year' => 1611];
        $imp->setBibleAttributes($data);

        $this->assertSame($data, $imp->getBibleAttributes());
    }

    // -----------------------------------------------------------------------
    // _postFormatText
    // -----------------------------------------------------------------------

    #[DataProvider('postFormatTextDataProvider')]
    public function testPostFormatText(string $input, string $expected): void
    {
        $imp = $this->makeImporter();
        $this->assertSame($expected, $imp->callPostFormatText($input));
    }

    public static function postFormatTextDataProvider(): array
    {
        return [
            'html tags stripped'             => ['<b>bold</b> text',    'bold text'],
            'multiple spaces collapsed'      => ['faith   hope   joy',  'faith hope joy'],
            'space before comma removed'     => ['faith , hope',        'faith, hope'],
            'space before period removed'    => ['the end .',           'the end.'],
            'space before semicolon removed' => ['wait ; here',         'wait; here'],
            'space before colon removed'     => ['John : 3',            'John: 3'],
            // Note: _postFormatText collapses whitespace but does NOT trim edges;
            // _preFormatText (called first in _formatText) handles trimming.
            'multiple leading spaces collapse to one'   => ['  text',   ' text'],
            'multiple trailing spaces collapse to one'  => ['text  ',   'text '],
        ];
    }

    // -----------------------------------------------------------------------
    // mapMetaToAttributes
    // -----------------------------------------------------------------------

    public function testMapMetaToAttributesSimpleMapping(): void
    {
        $imp = $this->makeImporter();

        // Use reflection to set a custom attribute_map
        $rp = new \ReflectionProperty(ImporterAbstract::class, 'attribute_map');
        $rp->setValue($imp, ['name' => 'title', 'year' => 'pub_year']);

        $imp->mapMetaToAttributes(['title' => 'Holy Bible', 'pub_year' => 1611]);

        $attrs = $imp->getBibleAttributes();
        $this->assertSame('Holy Bible', $attrs['name']);
        $this->assertSame(1611, $attrs['year']);
    }

    public function testMapMetaToAttributesWithoutPreserveBuildsFreshArray(): void
    {
        $imp = $this->makeImporter();

        $rp = new \ReflectionProperty(ImporterAbstract::class, 'attribute_map');
        $rp->setValue($imp, ['name' => 'title']);

        // Without preserve_attributes, only mapped keys present in meta end up in attrs.
        // 'title' is absent — so the result has no 'name' key at all.
        $imp->mapMetaToAttributes(['other' => 'something']);

        $this->assertArrayNotHasKey('name', $imp->getBibleAttributes());
    }

    public function testMapMetaToAttributesWithPreserveRetainsUnmatchedExistingKeys(): void
    {
        $imp = $this->makeImporter();
        $imp->setBibleAttributes(['name' => 'Existing', 'module' => 'mod']);

        $rp = new \ReflectionProperty(ImporterAbstract::class, 'attribute_map');
        $rp->setValue($imp, ['name' => 'title']);

        // preserve_attributes=true: start with existing values; 'title' absent → 'name' unchanged
        $imp->mapMetaToAttributes(['other' => 'something'], preserve_attributes: true);

        $this->assertSame('Existing', $imp->getBibleAttributes()['name']);
    }

    public function testMapMetaToAttributesWithPreserveDoesNotOverwriteEqualValues(): void
    {
        $imp = $this->makeImporter();
        $imp->setBibleAttributes(['name' => 'Same', 'module' => null]);

        $rp = new \ReflectionProperty(ImporterAbstract::class, 'attribute_map');
        $rp->setValue($imp, ['name' => 'title']);

        // When existing value equals the incoming meta value, it is not overwritten
        $imp->mapMetaToAttributes(['title' => 'Same'], preserve_attributes: true);

        $this->assertSame('Same', $imp->getBibleAttributes()['name']);
    }
}
