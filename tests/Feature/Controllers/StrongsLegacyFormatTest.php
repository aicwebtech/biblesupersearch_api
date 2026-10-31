<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Tests\Support\StrongsDictionaryFixtures;
use App\Engine;
use App\Models\VolumeTypes\Strongs;
use App\Models\StrongsDefinition;
use App\Engines\EngineV3;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy Strong's format answered from a modern dictionary, the legacy table filling in what
 * the dictionary does not hold, and the statics flags.
 *
 * Whether the legacy table may be used is the strongs_legacy feature; these tests set it through
 * an Engine subclass rather than writing the real feature row.
 */
class StrongsLegacyFormatTest extends TestCase
{
    use StrongsDictionaryFixtures;

    private const LANGUAGE = 'qql';
    private const LEGACY_FIELDS = ['id', 'number', 'root_word', 'transliteration', 'pronunciation', 'tvm', 'entry'];

    public function tearDown(): void
    {
        $this->removeStrongsDictionaries();
        parent::tearDown();
    }

    /**
     * A v2 Engine with the legacy table enabled or not, and optionally no usable dictionary
     */
    private function engine(bool $legacy, bool $modern = TRUE): Engine
    {
        $Engine = new class extends Engine {
            public bool $legacy = TRUE;
            public bool $modern = TRUE;

            protected function _legacyStrongsAvailable(): bool
            {
                return $this->legacy;
            }

            protected function _resolveStrongsDictionary(mixed $dictionary, mixed $language): Strongs|false|null
            {
                return $this->modern ? parent::_resolveStrongsDictionary($dictionary, $language) : NULL;
            }
        };

        $Engine->legacy = $legacy;
        $Engine->modern = $modern;

        return $Engine;
    }

    public function testV2DefaultIsAnsweredFromTheLanguageDictionaryInTheLegacyFormat(): void
    {
        $Volume = $this->makeDictionary();
        $this->createLanguageFixture(self::LANGUAGE, 'Strongs Legacy Format Fixture')->setAttr(Strongs::LANGUAGE_ATTR, $Volume->module);

        $Engine  = $this->engine(FALSE);
        $results = $Engine->actionStrongs(['strongs' => 'H1234 G5719', 'language' => self::LANGUAGE]);

        $this->assertFalse($Engine->hasErrors(), implode(' ', (array) $Engine->getErrors()));
        $this->assertCount(2, $results);

        foreach($results as $result) {
            $this->assertEqualsCanonicalizing(self::LEGACY_FIELDS, array_keys($result));
        }

        // An ordinary entry, and a tense/voice/mood entry in tvm
        $this->assertSame('H1234', $results[0]['number']);
        $this->assertSame('to <i>cleave</i>', $results[0]['entry']);
        $this->assertNull($results[0]['tvm']);
        $this->assertSame("\u{05D1}\u{05BC}\u{05E7}\u{05E2}", $results[0]['root_word']);

        $this->assertSame('G5719', $results[1]['number']);
        $this->assertNull($results[1]['entry']);
        $this->assertStringContainsString('Present', $results[1]['tvm']);
    }

    /**
     * Numbers the dictionary does not hold come from the legacy table, when it is enabled
     */
    public function testNumbersTheDictionaryLacksFallBackToTheLegacyTable(): void
    {
        $legacy_g1 = StrongsDefinition::where('number', 'G1')->first();

        if(!$legacy_g1) {
            $this->markTestSkipped('The legacy Strong\'s table is not installed');
        }

        $Volume = $this->makeDictionary();

        $Engine  = $this->engine(TRUE);
        $results = $Engine->actionStrongs(['strongs' => 'H1234 G1', 'dictionary' => $Volume->module]);

        $this->assertFalse($Engine->hasErrors(), implode(' ', (array) $Engine->getErrors()));
        $this->assertSame(['H1234', 'G1'], array_column($results, 'number'));
        $this->assertNotSame(1234, $results[0]['id'], 'H1234 came from the legacy table, not the dictionary');
        $this->assertSame($legacy_g1->id, $results[1]['id']);

        // Without the legacy table, G1 is simply not found
        $Engine  = $this->engine(FALSE);
        $results = $Engine->actionStrongs(['strongs' => 'H1234 G1', 'dictionary' => $Volume->module]);

        $this->assertSame(['H1234'], array_column($results, 'number'));
        $this->assertStringContainsString('G1', implode(' ', $Engine->getErrors()));
        $this->assertLessThan(4, $Engine->getErrorLevel());
    }

    public function testWithoutADictionaryTheLegacyTableAnswers(): void
    {
        if(!StrongsDefinition::where('number', 'H1234')->exists()) {
            $this->markTestSkipped('The legacy Strong\'s table is not installed');
        }

        $Engine  = $this->engine(TRUE, FALSE);
        $results = $Engine->actionStrongs(['strongs' => 'H1234']);

        $this->assertFalse($Engine->hasErrors(), implode(' ', (array) $Engine->getErrors()));
        $this->assertSame(1234, $results[0]['id']);
    }

    public function testWithNeitherSourceThereIsAnError(): void
    {
        $Engine = $this->engine(FALSE, FALSE);

        $this->assertFalse($Engine->actionStrongs(['strongs' => 'H1234']));
        $this->assertSame(4, $Engine->getErrorLevel());
        $this->assertStringContainsString(__('errors.strongs_no_dictionary'), implode(' ', $Engine->getErrors()));

        // v2 asking for modern has nothing to fall back to either
        $Engine = $this->engine(FALSE, FALSE);

        $this->assertFalse($Engine->actionStrongs(['strongs' => 'H1234', 'version' => 'modern']));
        $this->assertSame(4, $Engine->getErrorLevel());
    }

    /**
     * The legacy format is offered when either source can answer it
     */
    public function testStaticsLegacyFlagCountsModern(): void
    {
        $Volume = $this->makeDictionary();
        $this->assertTrue(Strongs::hasAvailable());

        $strongs = $this->engine(FALSE)->actionStatics([])->features['strongs'];

        $this->assertTrue($strongs['modern']['enabled']);
        $this->assertTrue($strongs['legacy']['enabled'], 'Modern can answer in the legacy format');
        $this->assertTrue($strongs['enabled']);
    }

    /**
     * A dictionary's text is untrusted, unlike the legacy table's: every text field of the
     * legacy format is sanitized
     */
    public function testDictionaryTextIsSanitizedInTheLegacyFormat(): void
    {
        $Volume = $this->makeDictionary();
        $attack = '<img src=x onerror=alert(1)>Bad';

        DB::table($Volume->content()->getTable())->insert([
            'number' => 'H9', 'root_word' => $attack, 'transliteration' => $attack, 'pronunciation' => $attack,
            'definition' => $attack, 'is_special' => 0,
        ]);

        $Engine  = $this->engine(FALSE);
        $results = $Engine->actionStrongs(['strongs' => 'H9', 'dictionary' => $Volume->module]);

        $this->assertFalse($Engine->hasErrors(), implode(' ', (array) $Engine->getErrors()));

        foreach(['root_word', 'transliteration', 'pronunciation', 'entry'] as $field) {
            $this->assertStringNotContainsString('onerror', (string) $results[0][$field], $field);
            $this->assertStringContainsString('Bad', (string) $results[0][$field], $field);
        }
    }

    /**
     * A dictionary uninstalled (its table dropped) after it was chosen, as an admin's Uninstall
     * or Update can do mid-request: no exception, but the next source or an error
     */
    public function testADictionaryWhoseTableIsGoneDoesNotFail(): void
    {
        $Volume = $this->makeDictionary();
        Schema::drop($Volume->content()->getTable());

        // v3's modern: no fallback, the usual error
        $Engine = new EngineV3();
        $this->assertSame([], $Engine->actionStrongs(['strongs' => 'H1234', 'dictionary' => $Volume->module]));
        $this->assertSame(4, $Engine->getErrorLevel());
        $this->assertStringContainsString(__('errors.strongs_no_dictionary'), implode(' ', $Engine->getErrors()));

        // Legacy with the legacy table enabled: the table answers
        if(StrongsDefinition::where('number', 'H1234')->exists()) {
            $Engine  = $this->engine(TRUE);
            $results = $Engine->actionStrongs(['strongs' => 'H1234', 'dictionary' => $Volume->module]);

            $this->assertFalse($Engine->hasErrors(), implode(' ', (array) $Engine->getErrors()));
            $this->assertSame(1234, $results[0]['id']);
        }

        // Legacy without it: the error, at the query action's non-fatal level
        $Engine = $this->engine(FALSE);
        $Engine->actionQuery(['bible' => 'kjv_strongs', 'search' => 'H1234', 'page_limit' => 1, 'strongs_dictionary' => $Volume->module]);

        $this->assertSame(3, $Engine->getErrorLevel());
        $this->assertStringContainsString(__('errors.strongs_no_dictionary'), implode(' ', $Engine->getErrors()));
    }
}
