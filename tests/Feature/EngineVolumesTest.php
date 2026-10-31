<?php

namespace Tests\Feature;

use App\Engine;
use App\Factories\EngineFactory;
use App\Models\Volume;
use App\Models\VolumeTypes\Strongs;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * The 'volumes' action: enabled volumes, grouped by type and indexed by module.
 *
 * Installed volumes other tests made may show up in the listing at any time, so assertions on
 * order only look at this test's own fixtures.  Every fixture is removed again in tearDown.
 */
class EngineVolumesTest extends TestCase
{
    private function engine(): Engine
    {
        return EngineFactory::getNewEngine();
    }

    /**
     * An installed (structure only) Strong's volume
     *
     * @param array<string, mixed> $attributes
     */
    private function makeVolume(array $attributes = [], bool $enable = TRUE, bool $install = TRUE): Strongs
    {
        $module = $this->fixtureModule('vol_list_');
        $this->deferCleanup(fn() => $this->removeVolumeModuleFixture('strongs', $module));

        $Volume = new Strongs();
        $Volume->module    = $module;
        $Volume->name      = $attributes['name'] ?? 'Volume List Fixture ' . $module;
        $Volume->shortname = 'VolList ' . $module;
        $Volume->language  = 'en';
        $Volume->fill($attributes);
        $Volume->save();

        if($install) {
            $this->assertTrue($Volume->install(TRUE, $enable));
        }

        return $Volume;
    }

    /**
     * Modules of the given fixtures, in the order the listing gives them
     *
     * @param array<string, mixed> $input
     * @param Strongs[] $Volumes
     * @return string[]
     */
    private function listedOrder(array $input, array $Volumes): array
    {
        $modules = array_map(fn(Strongs $Volume) => $Volume->module, $Volumes);
        $listed  = array_keys((array) $this->engine()->actionVolumes($input)['strongs']);

        return array_values(array_intersect($listed, $modules));
    }

    public function testEveryTypeHasAGroup(): void
    {
        $volumes = $this->engine()->actionVolumes([]);

        $this->assertSame(array_keys(Volume::getTypes()), array_keys($volumes));
    }

    /**
     * v3 lists the Bibles as the first group, the same listing as the bibles action
     */
    public function testV3ListsTheBiblesFirst(): void
    {
        $Engine  = EngineFactory::getNewEngine(3);
        $volumes = $Engine->actionVolumes([]);

        $this->assertSame(array_merge(['bibles'], array_keys(Volume::getTypes())), array_keys($volumes));
        $this->assertSame('KJV', $volumes['bibles']['kjv']['shortname']);
        $this->assertSame($Engine->actionBibles([]), $volumes['bibles']);
    }

    public function testOnlyInstalledEnabledVolumesAreListed(): void
    {
        $Enabled     = $this->makeVolume();
        $Disabled    = $this->makeVolume([], FALSE);
        $Uninstalled = $this->makeVolume([], FALSE, FALSE);

        $strongs = (array) $this->engine()->actionVolumes([])['strongs'];

        $this->assertArrayHasKey($Enabled->module, $strongs);
        $this->assertArrayNotHasKey($Disabled->module, $strongs);
        $this->assertArrayNotHasKey($Uninstalled->module, $strongs);
    }

    public function testVolumeFields(): void
    {
        $Volume = $this->makeVolume([
            'year' => '1890', 'owner' => 'Fixture Owner', 'publisher' => 'Fixture Publisher',
            'url' => 'https://example.com/', 'description' => '<p>A <b>fixture</b></p>',
            'copyright_statement' => '<p>Fixture statement</p>', 'rank' => 5,
        ]);

        $listed = $this->engine()->actionVolumes([])['strongs'][$Volume->module];

        $this->assertSame([
            'type', 'name', 'shortname', 'module', 'year', 'owner', 'publisher', 'description', 'url',
            'lang', 'lang_short', 'lang_native', 'rtl', 'copyright', 'copyright_id', 'copyright_statement',
            'rank', 'research', 'restrict', 'citation_limit', 'is_default',
        ], array_keys($listed));

        $this->assertSame('strongs', $listed['type']);
        $this->assertSame($Volume->name, $listed['name']);
        $this->assertSame('en', $listed['lang_short']);
        $this->assertSame('English', $listed['lang']);
        $this->assertSame('Fixture Publisher', $listed['publisher']);
        $this->assertSame('<p>A <b>fixture</b></p>', $listed['description']);
        $this->assertSame('<p>Fixture statement</p>', $listed['copyright_statement']);
        $this->assertSame(5, (int) $listed['rank']);
        $this->assertFalse($listed['is_default']);

        foreach(['audio_enable', 'tts_enable', 'tts_ai', 'audio_structure', 'downloadable', 'book_list'] as $field) {
            $this->assertArrayNotHasKey($field, $listed, $field);
        }
    }

    /**
     * With no statement of its own, the statement is generated from the copyright, as for Bibles,
     * naming the volume's type where the stored statement says 'This Bible'
     */
    public function testCopyrightStatementFallsBackToTheCopyright(): void
    {
        $copyright_id = (int) \App\Models\Copyright::where('cr_name', 'public_domain')->value('id');
        $Volume = $this->makeVolume(['copyright_id' => $copyright_id]);

        $listed = $this->engine()->actionVolumes([])['strongs'][$Volume->module];

        $this->assertSame($Volume->getCopyrightStatement(), $listed['copyright_statement']);
        $this->assertStringContainsString('This Strong\'s Dictionary is in the Public Domain.', $listed['copyright_statement']);
        $this->assertStringNotContainsString('Bible', $listed['copyright_statement']);
    }

    public function testDefaultOrderIsRankThenName(): void
    {
        $Third  = $this->makeVolume(['rank' => 20, 'name' => 'Volume List A ' . bin2hex(random_bytes(3))]);
        $Second = $this->makeVolume(['rank' => 10, 'name' => 'Volume List B ' . bin2hex(random_bytes(3))]);
        $First  = $this->makeVolume(['rank' => 10, 'name' => 'Volume List A ' . bin2hex(random_bytes(3))]);

        $this->assertSame(
            [$First->module, $Second->module, $Third->module],
            $this->listedOrder([], [$Third, $Second, $First])
        );
    }

    public function testCustomOrder(): void
    {
        $B = $this->makeVolume(['rank' => 1, 'name' => 'Volume List B ' . bin2hex(random_bytes(3))]);
        $A = $this->makeVolume(['rank' => 2, 'name' => 'Volume List A ' . bin2hex(random_bytes(3))]);

        $this->assertSame([$A->module, $B->module], $this->listedOrder(['bible_order_by' => 'name'], [$A, $B]));
        $this->assertSame([$B->module, $A->module], $this->listedOrder(['bible_order_by' => 'rank'], [$A, $B]));
    }

    /**
     * The legacy flag of the Bible list: English language name, then name
     */
    public function testLegacyOrderByLangName(): void
    {
        $English = $this->makeVolume(['rank' => 1, 'name' => 'Volume List A ' . bin2hex(random_bytes(3))]);
        $German  = $this->makeVolume(['rank' => 2, 'language' => 'de', 'name' => 'Volume List B ' . bin2hex(random_bytes(3))]);

        $this->assertSame(
            [$English->module, $German->module],
            $this->listedOrder(['order_by_lang_name' => 1, 'bible_order_by' => 'rank'], [$English, $German])
        );

        $German->rank = 0;
        $German->save();

        $this->assertSame([$English->module, $German->module], $this->listedOrder(['order_by_lang_name' => 1], [$English, $German]));
        $this->assertSame([$German->module, $English->module], $this->listedOrder([], [$English, $German]));
    }

    public function testUnknownOrderValuesAreIgnored(): void
    {
        $B = $this->makeVolume(['name' => 'Volume List B ' . bin2hex(random_bytes(3))]);
        $A = $this->makeVolume(['name' => 'Volume List A ' . bin2hex(random_bytes(3))]);

        foreach(['nonsense', 'id; DROP TABLE x', ['rank']] as $order_by) {
            $this->assertSame([$A->module, $B->module], $this->listedOrder(['bible_order_by' => $order_by], [$A, $B]));
        }
    }

    /**
     * As in the Bible listing: the float lists that language first under a language ordering,
     * and is discarded under rank, name or shortname, which a language block cannot be lifted
     * out of without contradicting.  The default ordering ends in rank.
     */
    public function testLanguageFloatFollowsTheBibleListing(): void
    {
        // English sorts first under every ordering used here, so a German first is the float's doing
        $suffix  = bin2hex(random_bytes(3));
        $English = $this->makeVolume(['rank' => 1, 'name' => 'Volume List A ' . $suffix, 'shortname' => 'VolList A ' . $suffix]);
        $German  = $this->makeVolume(['rank' => 2, 'language' => 'de', 'name' => 'Volume List B ' . $suffix, 'shortname' => 'VolList B ' . $suffix]);

        $this->assertSame([$English->module, $German->module], $this->listedOrder(['bible_order_by' => 'lang_name_english'], [$English, $German]));

        $listed = $this->listedOrder(['bible_order_by' => 'lang_name_english', 'language_float' => 'de'], [$English, $German]);
        $this->assertSame([$German->module, $English->module], $listed, 'lang_name_english');

        $listed = $this->listedOrder(['order_by_lang_name' => 1, 'language_float' => 'de'], [$English, $German]);
        $this->assertSame([$German->module, $English->module], $listed, 'order_by_lang_name');

        foreach(['rank', 'name', 'shortname', 'lang_name_english|rank'] as $order_by) {
            $listed = $this->listedOrder(['bible_order_by' => $order_by, 'language_float' => 'de'], [$English, $German]);
            $this->assertSame([$English->module, $German->module], $listed, $order_by);
        }

        $this->assertSame([$English->module, $German->module], $this->listedOrder(['language_float' => 'de'], [$English, $German]), 'default');
    }

    public function testStaticsIncludesTheVolumes(): void
    {
        $Volume  = $this->makeVolume();
        $statics = $this->engine()->actionStatics([]);

        $this->assertArrayHasKey($Volume->module, (array) $statics->volumes['strongs']);
    }

    /**
     * v3 answers in Markdown, the description and copyright statement included
     */
    public function testV3AnswersInMarkdown(): void
    {
        $Volume = $this->makeVolume(['description' => '<p>A <b>fixture</b></p>']);

        $listed = EngineFactory::getNewEngine(3)->actionVolumes([])['strongs'][$Volume->module];

        $this->assertSame('A **fixture**', $listed['description']);
    }

    public function testHttpAction(): void
    {
        $Volume = $this->makeVolume();

        $response = $this->withoutMiddleware(ThrottleRequests::class)->getJson('/api/volumes');

        $response->assertStatus(200);
        $this->assertSame(0, $response['error_level']);
        $this->assertSame($Volume->shortname, $response['results']['strongs'][$Volume->module]['shortname']);
    }
}
