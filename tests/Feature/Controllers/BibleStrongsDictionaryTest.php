<?php

namespace Tests\Feature\Controllers;

use App\Models\Bible;
use App\Models\Copyright;
use App\Models\LanguageAttr;
use App\Models\Volume;
use App\Models\VolumeAttr;
use App\Models\VolumeTypes\Strongs;
use App\User;
use Tests\Support\StrongsDictionaryFixtures;
use Tests\TestCase;

/**
 * The admin Bible edit dialog's default Strong's dictionary, kept in volumes_attr
 * (type 'bible', the Bible's module, Strongs::BIBLE_ATTR) rather than on the bibles row
 */
class BibleStrongsDictionaryTest extends TestCase
{
    use StrongsDictionaryFixtures;

    public function tearDown(): void
    {
        $this->removeStrongsDictionaries();

        parent::tearDown();
    }

    protected function admin(): User
    {
        $User = User::find(1);
        $this->assertGreaterThanOrEqual(100, $User->access_level);

        return $User;
    }

    /**
     * An uninstalled, unofficial throwaway Bible: enough for the edit dialog, and deletable
     */
    protected function makeBibleFixture(): Bible
    {
        $module = 'test_bible_dict_' . bin2hex(random_bytes(3));

        $this->deferCleanup(function() use ($module) {
            Bible::where('module', $module)->delete();
            VolumeAttr::unsetAll(VolumeAttr::TYPE_BIBLE, $module);
        });

        $Bible = new Bible();
        $Bible->module       = $module;
        $Bible->name         = 'Bible Dictionary Fixture ' . $module;
        $Bible->shortname    = 'BibDict ' . $module;
        $Bible->year         = '2000';
        $Bible->lang_short   = 'en';
        $Bible->copyright_id = (int) Copyright::value('id');
        $Bible->save();

        return $Bible;
    }

    protected function getDictionary(string $module): ?string
    {
        return VolumeAttr::getAttr(VolumeAttr::TYPE_BIBLE, $module, Strongs::BIBLE_ATTR);
    }

    /**
     * The record as the edit dialog sends it back, with changes
     *
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    protected function input(Bible $Bible, array $changes = []): array
    {
        $record = $this->actingAs($this->admin())->getJson('/admin/bibles/' . $Bible->id)->assertStatus(200)->json('Bible');
        unset($record['strongs_dictionary']);

        return array_replace($record, $changes);
    }

    public function testIndexBootstrapListsAvailableDictionaries(): void
    {
        $Enabled  = $this->makeDictionary();
        $Disabled = $this->makeDictionary(FALSE);

        $response = $this->actingAs($this->admin())->get('/admin/bibles')->assertStatus(200);

        $response->assertSee('strongs_dictionaries', FALSE);
        $response->assertSee($Enabled->module, FALSE);
        $response->assertDontSee($Disabled->module, FALSE);
    }

    public function testShowReportsTheDictionary(): void
    {
        $Volume = $this->makeDictionary();
        $Bible  = $this->makeBibleFixture();

        $this->actingAs($this->admin())->getJson('/admin/bibles/' . $Bible->id)
            ->assertStatus(200)
            ->assertJsonPath('Bible.strongs_dictionary', NULL);

        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $Bible->module, Strongs::BIBLE_ATTR, $Volume->module);

        $this->actingAs($this->admin())->getJson('/admin/bibles/' . $Bible->id)
            ->assertJsonPath('Bible.strongs_dictionary', $Volume->module);
    }

    public function testUpdateSetsKeepsAndClearsTheDictionary(): void
    {
        $Volume = $this->makeDictionary();
        $Bible  = $this->makeBibleFixture();

        $this->actingAs($this->admin())->putJson('/admin/bibles/' . $Bible->id, $this->input($Bible, ['strongs_dictionary' => $Volume->module]))
            ->assertStatus(200)
            ->assertJsonPath('Bible.strongs_dictionary', $Volume->module);

        $this->assertSame($Volume->module, $this->getDictionary($Bible->module));

        // Omitted: left alone
        $this->actingAs($this->admin())->putJson('/admin/bibles/' . $Bible->id, $this->input($Bible))->assertStatus(200);
        $this->assertSame($Volume->module, $this->getDictionary($Bible->module));

        // Empty: removed
        $this->actingAs($this->admin())->putJson('/admin/bibles/' . $Bible->id, $this->input($Bible, ['strongs_dictionary' => NULL]))
            ->assertStatus(200)
            ->assertJsonPath('Bible.strongs_dictionary', NULL);

        $this->assertNull($this->getDictionary($Bible->module));
    }

    /**
     * A dictionary that is not installed and enabled is refused - unless it is the one already
     * stored, which the dialog sends back with every save
     */
    public function testUpdateRejectsAnUnavailableDictionary(): void
    {
        $Disabled = $this->makeDictionary(FALSE);
        $Bible    = $this->makeBibleFixture();

        foreach([$Disabled->module, 'no_such_dictionary'] as $module) {
            $this->actingAs($this->admin())->putJson('/admin/bibles/' . $Bible->id, $this->input($Bible, ['strongs_dictionary' => $module]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('strongs_dictionary', 'errors');

            $this->assertNull($this->getDictionary($Bible->module));
        }

        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $Bible->module, Strongs::BIBLE_ATTR, $Disabled->module);

        $this->actingAs($this->admin())->putJson('/admin/bibles/' . $Bible->id, $this->input($Bible, ['strongs_dictionary' => $Disabled->module]))
            ->assertStatus(200);
    }

    public function testChangingTheDictionaryTouchesTheVolumes(): void
    {
        $Volume = $this->makeDictionary();
        $Bible  = $this->makeBibleFixture();
        $old    = '2001-01-01 00:00:00';

        Volume::whereKey($Volume->id)->update(['updated_at' => $old]);

        $this->actingAs($this->admin())->putJson('/admin/bibles/' . $Bible->id, $this->input($Bible, ['strongs_dictionary' => $Volume->module]))
            ->assertStatus(200);

        $this->assertNotSame($old, (string) Volume::whereKey($Volume->id)->value('updated_at'));
    }

    public function testRenamingTheModuleKeepsTheDictionary(): void
    {
        $Volume = $this->makeDictionary();
        $Bible  = $this->makeBibleFixture();
        $old    = $Bible->module;
        $new    = $old . '_renamed';

        $this->deferCleanup(function() use ($new) {
            Bible::where('module', $new)->delete();
            VolumeAttr::unsetAll(VolumeAttr::TYPE_BIBLE, $new);
        });

        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $old, Strongs::BIBLE_ATTR, $Volume->module);

        $this->actingAs($this->admin())->putJson('/admin/bibles/' . $Bible->id, $this->input($Bible, ['module' => $new]))
            ->assertStatus(200)
            ->assertJsonPath('Bible.strongs_dictionary', $Volume->module);

        $this->assertSame($Volume->module, $this->getDictionary($new));
        $this->assertNull($this->getDictionary($old));
    }

    /**
     * Settings left under the new module, by a delete that skipped the model's events, would
     * break the unique key part way through the save and lose the dictionary: they are stale,
     * as no other Bible has that module, and are replaced
     */
    public function testRenamingOverLeftoverSettingsKeepsTheDictionary(): void
    {
        $Volume = $this->makeDictionary();
        $Bible  = $this->makeBibleFixture();
        $old    = $Bible->module;
        $new    = $old . '_renamed';

        $this->deferCleanup(function() use ($new) {
            Bible::where('module', $new)->delete();
            VolumeAttr::unsetAll(VolumeAttr::TYPE_BIBLE, $new);
        });

        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $old, Strongs::BIBLE_ATTR, $Volume->module);
        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $new, Strongs::BIBLE_ATTR, 'leftover_dictionary');

        $this->actingAs($this->admin())->putJson('/admin/bibles/' . $Bible->id, $this->input($Bible, ['module' => $new]))
            ->assertStatus(200)
            ->assertJsonPath('Bible.strongs_dictionary', $Volume->module);

        $this->assertSame($Volume->module, $this->getDictionary($new));
        $this->assertSame(1, VolumeAttr::where('type', VolumeAttr::TYPE_BIBLE)->where('module', $new)->count());
        $this->assertNull($this->getDictionary($old));
    }

    public function testDeletingTheBibleRemovesItsDictionary(): void
    {
        $Volume = $this->makeDictionary();
        $Bible  = $this->makeBibleFixture();

        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $Bible->module, Strongs::BIBLE_ATTR, $Volume->module);

        $this->actingAs($this->admin())->deleteJson('/admin/bibles/' . $Bible->id)->assertStatus(200);

        $this->assertNull($this->getDictionary($Bible->module));
    }

    /**
     * However the Bible is deleted - here as the uninstall command and an import do, by the
     * model rather than the admin page - its settings go with it, so a new Bible given the same
     * module does not inherit them
     */
    public function testDeletingTheBibleModelRemovesItsDictionary(): void
    {
        $Volume = $this->makeDictionary();
        $Bible  = $this->makeBibleFixture();

        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $Bible->module, Strongs::BIBLE_ATTR, $Volume->module);

        $Bible->delete();

        $this->assertNull($this->getDictionary($Bible->module));
    }

    /**
     * Deleting a dictionary removes it from every Bible that had it as the default
     */
    public function testDeletingTheDictionaryRemovesItFromBibles(): void
    {
        $Volume = $this->makeDictionary();
        $Bible  = $this->makeBibleFixture();

        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $Bible->module, Strongs::BIBLE_ATTR, $Volume->module);

        $this->actingAs($this->admin())->deleteJson('/admin/volumes/' . $Volume->id)->assertStatus(200);

        $this->assertNull($this->getDictionary($Bible->module));
    }

    /**
     * However the dictionary is deleted - here by the model, not the admin page - no Bible or
     * language keeps it as their default: a new dictionary given the same module later would
     * silently take its place
     */
    public function testDeletingTheDictionaryModelRemovesItFromBiblesAndLanguages(): void
    {
        $Volume = $this->makeDictionary();
        $Bible  = $this->makeBibleFixture();
        $code   = 'qqh';

        $this->deferCleanup(fn() => LanguageAttr::where('code', $code)->delete());

        VolumeAttr::setAttr(VolumeAttr::TYPE_BIBLE, $Bible->module, Strongs::BIBLE_ATTR, $Volume->module);
        LanguageAttr::create(['code' => $code, 'attribute' => Strongs::LANGUAGE_ATTR, 'value' => $Volume->module]);

        $Volume->uninstall();
        $Volume->delete();

        $this->assertNull($this->getDictionary($Bible->module));
        $this->assertFalse(LanguageAttr::where('code', $code)->exists());
    }
}
