<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\VolumeTypes\Strongs;

/**
 * Strongs::findAvailable() and the language step of Strongs::resolveDefault().
 *
 * The global default step is covered by StrongsApiTest::testGlobalDefaultAndNoDictionary(),
 * the one test that changes the Strong's default: keeping every reader and writer of that
 * flag in one test method stops them racing when the suite runs in parallel.
 */
class StrongsResolveTest extends TestCase
{
    private const LANGUAGE = 'qqr';

    protected function makeDictionary(bool $enabled = TRUE): Strongs
    {
        $suffix = bin2hex(random_bytes(3));

        $Volume = new Strongs();
        $Volume->module    = 'vol_resolve_' . $suffix;
        $Volume->name      = 'Resolve Fixture ' . $suffix;
        $Volume->shortname = 'Resolve ' . $suffix;
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

    public function testFindAvailable(): void
    {
        $Enabled = $Disabled = NULL;

        try {
            $Enabled  = $this->makeDictionary();
            $Disabled = $this->makeDictionary(FALSE);

            $this->assertSame($Enabled->id, Strongs::findAvailable($Enabled->module)?->id);
            $this->assertNull(Strongs::findAvailable($Disabled->module));
            $this->assertNull(Strongs::findAvailable('no_such_dictionary'));
        }
        finally {
            $this->removeDictionary($Disabled);
            $this->removeDictionary($Enabled);
        }
    }

    public function testAvailableDictionariesListsOnlyEnabledOnes(): void
    {
        $Enabled = $Disabled = NULL;

        try {
            $Enabled  = $this->makeDictionary();
            $Disabled = $this->makeDictionary(FALSE);

            $modules = Strongs::availableDictionaries()->pluck('module')->all();

            $this->assertContains($Enabled->module, $modules);
            $this->assertNotContains($Disabled->module, $modules);
        }
        finally {
            $this->removeDictionary($Disabled);
            $this->removeDictionary($Enabled);
        }
    }

    public function testResolveDefaultUsesTheLanguageDictionary(): void
    {
        $Volume = NULL;

        try {
            $Volume   = $this->makeDictionary();
            $Language = $this->createLanguageFixture(self::LANGUAGE, 'Strongs Resolve Fixture Language');
            $Language->setAttr(Strongs::LANGUAGE_ATTR, $Volume->module);

            $this->assertSame($Volume->id, Strongs::resolveDefault(self::LANGUAGE)?->id);
        }
        finally {
            $this->removeDictionary($Volume);
            $this->removeLanguageFixture(self::LANGUAGE);
        }
    }

    /**
     * Only the language step is asserted: what comes back instead is the global default,
     * whatever that is at the time.
     */
    public function testResolveDefaultSkipsAnUnusableLanguageDictionary(): void
    {
        $Volume = NULL;

        try {
            $Volume   = $this->makeDictionary(FALSE);
            $Language = $this->createLanguageFixture(self::LANGUAGE, 'Strongs Resolve Fixture Language');
            $Language->setAttr(Strongs::LANGUAGE_ATTR, $Volume->module);

            $this->assertNotSame($Volume->id, Strongs::resolveDefault(self::LANGUAGE)?->id);
        }
        finally {
            $this->removeDictionary($Volume);
            $this->removeLanguageFixture(self::LANGUAGE);
        }
    }
}
