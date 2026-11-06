<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\InstallManager;
use App\Models\Volume;
use App\Models\VolumeTypes\Strongs;
use Illuminate\Support\Facades\Log;

/**
 * The installer's optional default Strong's dictionary step.  Read only: it is only run where
 * it has nothing to write.
 */
class InstallManagerDefaultStrongsTest extends TestCase
{
    public function testPastTheTimeBudgetNothingIsInstalled(): void
    {
        Log::spy();

        $before = Strongs::findByModule(InstallManager::DEFAULT_STRONGS_MODULE)?->updated_at;

        $this->assertFalse(InstallManager::installDefaultStrongs(1000.0, 1000.0 + InstallManager::DEFAULT_STRONGS_TIME_BUDGET + 1));

        $this->assertEquals($before, Strongs::findByModule(InstallManager::DEFAULT_STRONGS_MODULE)?->updated_at);
        Log::shouldHaveReceived('info')->withArgs(fn($message) => str_contains($message, 'Features page'))->once();
    }

    /**
     * Within the budget, a dictionary that is already installed and the default is left alone
     */
    public function testWithinTheBudgetAnInstalledDefaultIsKept(): void
    {
        $Volume = Strongs::findByModule(InstallManager::DEFAULT_STRONGS_MODULE);

        if(!$Volume || !$Volume->installed || !$Volume->enabled || Volume::getDefault('strongs')?->id !== $Volume->id) {
            $this->markTestSkipped('The default Strong\'s dictionary is not installed as the default here; the step would write to it');
        }

        $before = $Volume->updated_at;

        $this->assertTrue(InstallManager::installDefaultStrongs(1000.0, 1000.0));
        $this->assertEquals($before, $Volume->refresh()->updated_at);
    }
}
