<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\User;
use App\Models\Language;
use App\Models\VolumeTypes\Strongs;

/**
 * The default Strong's dictionary on the Languages edit dialog: a language attribute, read
 * by show() and written by update().
 */
class LanguageConfigControllerTest extends TestCase
{
    private const LANGUAGE = 'qqt';

    protected $User = NULL;

    public function setUp(): void
    {
        parent::setUp();

        $this->User = new User;
        $this->User->name = 'Test Admin';
        $this->User->access_level = 100;
    }

    protected function admin()
    {
        return $this->actingAs($this->User)->withSession(['banned' => FALSE]);
    }

    protected function makeDictionary(bool $enabled = TRUE): Strongs
    {
        $suffix = bin2hex(random_bytes(3));

        $Volume = new Strongs();
        $Volume->module    = 'vol_langcfg_' . $suffix;
        $Volume->name      = 'Language Config Fixture ' . $suffix;
        $Volume->shortname = 'LangCfg ' . $suffix;
        $Volume->language  = 'en';
        $Volume->save();
        $Volume->install(FALSE, $enabled);

        return $Volume;
    }

    protected function removeDictionary(?Strongs $Volume): void
    {
        if($Volume) {
            $Volume->content()->uninstall();
            $Volume->forceDelete();
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function input(Language $Language, array $overrides = []): array
    {
        return array_merge([
            'code'        => $Language->code,
            'name'        => $Language->name,
            'native_name' => $Language->native_name,
        ], $overrides);
    }

    public function testShowIncludesTheStrongsDictionary(): void
    {
        $Volume = NULL;

        try {
            $Volume   = $this->makeDictionary();
            $Language = $this->createLanguageFixture(self::LANGUAGE, 'Language Config Fixture');

            $this->admin()->getJson('/admin/languages/' . $Language->id)
                ->assertStatus(200)
                ->assertJsonPath('Language.strongs_dictionary', NULL);

            $Language->setAttr(Strongs::LANGUAGE_ATTR, $Volume->module);

            $this->admin()->getJson('/admin/languages/' . $Language->id)
                ->assertJsonPath('Language.strongs_dictionary', $Volume->module);
        }
        finally {
            $this->removeDictionary($Volume);
            $this->removeLanguageFixture(self::LANGUAGE);
        }
    }

    public function testUpdateSetsAndClearsTheStrongsDictionary(): void
    {
        $Volume = NULL;

        try {
            $Volume   = $this->makeDictionary();
            $Language = $this->createLanguageFixture(self::LANGUAGE, 'Language Config Fixture');

            $this->admin()->putJson('/admin/languages/' . $Language->id, $this->input($Language, ['strongs_dictionary' => $Volume->module]))
                ->assertStatus(200)
                ->assertJsonPath('Language.strongs_dictionary', $Volume->module);

            $this->assertSame($Volume->module, Language::getLanguageAttr(self::LANGUAGE, Strongs::LANGUAGE_ATTR));

            // Omitted: left alone
            $this->admin()->putJson('/admin/languages/' . $Language->id, $this->input($Language))->assertStatus(200);
            $this->assertSame($Volume->module, Language::getLanguageAttr(self::LANGUAGE, Strongs::LANGUAGE_ATTR));

            // Empty: removed
            $this->admin()->putJson('/admin/languages/' . $Language->id, $this->input($Language, ['strongs_dictionary' => NULL]))
                ->assertStatus(200)
                ->assertJsonPath('Language.strongs_dictionary', NULL);

            $this->assertNull(Language::getLanguageAttr(self::LANGUAGE, Strongs::LANGUAGE_ATTR));
        }
        finally {
            $this->removeDictionary($Volume);
            $this->removeLanguageFixture(self::LANGUAGE);
        }
    }

    public function testUpdateRejectsAnUnavailableDictionary(): void
    {
        $Volume = NULL;

        try {
            $Volume   = $this->makeDictionary(FALSE);
            $Language = $this->createLanguageFixture(self::LANGUAGE, 'Language Config Fixture');

            foreach([$Volume->module, 'no_such_dictionary'] as $module) {
                $this->admin()->putJson('/admin/languages/' . $Language->id, $this->input($Language, ['strongs_dictionary' => $module]))
                    ->assertStatus(422)
                    ->assertJsonStructure(['errors' => ['strongs_dictionary']]);
            }

            $this->assertNull(Language::getLanguageAttr(self::LANGUAGE, Strongs::LANGUAGE_ATTR));
        }
        finally {
            $this->removeDictionary($Volume);
            $this->removeLanguageFixture(self::LANGUAGE);
        }
    }

    /**
     * The edit form sends the stored dictionary back with every save.  Once that dictionary is
     * disabled, refusing it would block any change to the language.
     */
    public function testSavingKeepsADictionaryThatIsNoLongerAvailable(): void
    {
        $Volume = $Other = NULL;

        try {
            $Volume   = $this->makeDictionary();
            $Other    = $this->makeDictionary(FALSE);
            $Language = $this->createLanguageFixture(self::LANGUAGE, 'Language Config Fixture');
            $Language->setAttr(Strongs::LANGUAGE_ATTR, $Volume->module);
            $Volume->disable();

            $this->admin()->putJson('/admin/languages/' . $Language->id, $this->input($Language, [
                'strongs_dictionary' => $Volume->module,
                'common_words'       => "a\nthe",
            ]))->assertStatus(200);

            $this->assertSame("a\nthe", $Language->refresh()->common_words);
            $this->assertSame($Volume->module, Language::getLanguageAttr(self::LANGUAGE, Strongs::LANGUAGE_ATTR));

            // Changing to another unavailable dictionary is still refused
            $this->admin()->putJson('/admin/languages/' . $Language->id, $this->input($Language, ['strongs_dictionary' => $Other->module]))
                ->assertStatus(422);
        }
        finally {
            $this->removeDictionary($Other);
            $this->removeDictionary($Volume);
            $this->removeLanguageFixture(self::LANGUAGE);
        }
    }

    public function testANonStringDictionaryIsAValidationError(): void
    {
        try {
            $Language = $this->createLanguageFixture(self::LANGUAGE, 'Language Config Fixture');

            $this->admin()->putJson('/admin/languages/' . $Language->id, $this->input($Language, ['strongs_dictionary' => ['x']]))
                ->assertStatus(422)
                ->assertJsonStructure(['errors' => ['strongs_dictionary']]);
        }
        finally {
            $this->removeLanguageFixture(self::LANGUAGE);
        }
    }

    public function testIndexProvidesTheAvailableDictionaries(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeDictionary();

            $this->admin()->get('/admin/languages')
                ->assertStatus(200)
                ->assertSee('strongs_dictionaries', FALSE)
                ->assertSee($Volume->module, FALSE);
        }
        finally {
            $this->removeDictionary($Volume);
        }
    }
}
