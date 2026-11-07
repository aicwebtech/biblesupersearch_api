<?php

namespace Tests\Unit\Features;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use App\Features\FeatureDefinitions;
use App\Models\Volume;

class FeatureDefinitionsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function strongsPresetProvider(): array
    {
        $presets = [];

        foreach(FeatureDefinitions::STRONGS_PRESETS as $language => $modules) {
            foreach($modules as $module) {
                $presets[$language . ': ' . $module] = [$language, $module];
            }
        }

        return $presets;
    }

    /**
     * A preset whose module file is gone fails the feature's install, and with it
     * app:install-testing in CI: this says which one, before anything is installed.
     */
    #[DataProvider('strongsPresetProvider')]
    public function testEveryStrongsPresetHasAnOfficialModuleFile(string $language, string $module): void
    {
        $path = dirname(__DIR__, 3) . '/content/strongs/' . Volume::moduleFileName('strongs', $module);

        $this->assertFileExists($path, 'The ' . $language . ' Strong\'s preset names ' . $module . ', which has no module file');
    }
}
