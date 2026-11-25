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

    /**
     * Only the volume features offer the 'all' actions, and only those FeatureController::all()
     * routes to
     */
    public function testAllActionsAreOfferedByVolumeFeaturesOnly(): void
    {
        $all = FeatureDefinitions::allActions();

        $this->assertSame(['strongs'], array_keys($all));
        $this->assertSame(FeatureDefinitions::ALL_ACTIONS, $all['strongs']['actions']);
        $this->assertSame("Strong's Dictionaries", $all['strongs']['name']);
    }

    /**
     * The 'all' callbacks' contracts, checked without running them: they act on every volume of
     * the type.  Install takes the enable flag; the others take nothing.
     */
    public function testAllCallbacksTakeTheDocumentedArguments(): void
    {
        $callbacks = FeatureDefinitions::find('strongs')['all'];

        foreach(FeatureDefinitions::ALL_ACTIONS as $action) {
            $Function = new \ReflectionFunction($callbacks[$action]);
            $params   = $Function->getParameters();

            $this->assertSame('bool', (string) $Function->getReturnType(), $action);

            if($action === 'install') {
                $this->assertCount(1, $params);
                $this->assertSame('bool', (string) $params[0]->getType());
                $this->assertTrue($params[0]->isOptional());
            }
            else {
                $this->assertCount(0, $params, $action);
            }
        }
    }
}
