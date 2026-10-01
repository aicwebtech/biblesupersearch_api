<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Models\Volume;
use App\Models\VolumeContent\ContentInterface;

class VolumeTest extends TestCase
{
    public function testTableName(): void
    {
        $this->assertSame('volumes', (new Volume())->getTable());
    }

    public function testDefaultAttributes(): void
    {
        $Volume = new Volume();

        $this->assertNull($Volume->copyright_id);
        $this->assertSame(1000, $Volume->rank);
    }

    public function testStrongsIsARegisteredType(): void
    {
        $this->assertArrayHasKey('strongs', Volume::getTypes());
        $this->assertSame('Strong\'s Dictionary', Volume::getType('strongs')['label']);
    }

    public function testEveryTypeHasALabel(): void
    {
        foreach(Volume::getTypes() as $type => $settings) {
            $this->assertIsArray($settings, $type);
            $this->assertIsString($settings['label'] ?? NULL, $type . ' has no label');
            $this->assertNotSame('', $settings['label'], $type . ' has an empty label');
        }
    }

    /**
     * Loading each registered class is what catches signature clashes with Volume and
     * ContentInterface: PHP reports those as fatal errors at class load time.
     */
    public function testEveryTypeClassLoads(): void
    {
        foreach(Volume::getTypes() as $type => $settings) {
            $this->assertTrue(is_subclass_of($settings['volume_class'], Volume::class), $type . ' volume_class must extend Volume');
            $this->assertTrue(is_subclass_of($settings['content_class'], ContentInterface::class), $type . ' content_class must implement ContentInterface');
            $this->assertSame($type, $settings['volume_class']::getTypeName());
            $this->assertSame($type, (new $settings['volume_class']())->type);
        }
    }

    public function testContentResolvesTheTypesContentClassAndTable(): void
    {
        $Volume = new Volume();
        $Volume->type = 'strongs';
        $Volume->module = 'strongs_ru';

        $Content = $Volume->content();

        $this->assertInstanceOf(Volume::getContentClassName('strongs'), $Content);
        $this->assertSame('stro_strongs_ru', $Content->getTable());
        $this->assertSame($Content, $Volume->content(), 'content() should reuse its instance');
    }

    public function testContentRequiresTypeAndModule(): void
    {
        $Volume = new Volume();
        $Volume->type = 'strongs';

        $this->expectException(\Exception::class);
        $Volume->content();
    }

    public function testUnknownTypeHasNoSettings(): void
    {
        $this->assertNull(Volume::getType('not_a_type'));
    }

    public function testTypeAndModuleAreImmutable(): void
    {
        $this->assertSame(['type', 'module'], Volume::IMMUTABLE_FIELDS);
    }

    public function testEveryUpdateRuleIsMassAssignable(): void
    {
        $fillable = (new Volume())->getFillable();

        foreach(array_keys(Volume::getUpdateRules(NULL, 'strongs')) as $field) {
            $this->assertContains($field, $fillable, $field . ' is validated but not fillable');
        }
    }

    public function testInstallStateFieldsAreNotMassAssignable(): void
    {
        $fillable = (new Volume())->getFillable();

        foreach(['installed', 'enabled', 'installed_at', 'id'] as $field) {
            $this->assertNotContains($field, $fillable);
        }
    }

    public function testUpdateRulesRestrictTypeToRegisteredTypes(): void
    {
        $rules = Volume::getUpdateRules(NULL, 'strongs');

        $this->assertContains('required', $rules['type']);
        $this->assertSame('in:"strongs"', (string) $rules['type'][1]);
    }

    public function testModuleIsLowercased(): void
    {
        $Volume = new Volume();
        $Volume->module = 'KJV_Strongs';

        $this->assertSame('kjv_strongs', $Volume->module);
    }

    public function testCannotBeEnabledWhileNotInstalled(): void
    {
        $Volume = new Volume();
        $Volume->enabled = 1;

        $this->assertSame(0, $Volume->enabled);

        $Volume->installed = 1;
        $Volume->enabled = 1;

        $this->assertSame(1, $Volume->enabled);
    }

    #[DataProvider('validModuleProvider')]
    public function testValidModule(string $module): void
    {
        $this->assertTrue(Volume::validateModule($module));
    }

    public static function validModuleProvider(): array
    {
        return [
            'letters'           => ['strongs'],
            'letters digits'    => ['bdb2'],
            'underscores'       => ['strongs_ru_1'],
            // No PHP class is generated from a volume module, so reserved words are allowed
            'php reserved word' => ['class'],
        ];
    }

    #[DataProvider('invalidModuleProvider')]
    public function testInvalidModule(mixed $module, string $reason): void
    {
        $this->assertFalse(Volume::validateModule($module));
        $this->assertSame($reason, Volume::getModuleInvalidReason());
    }

    public static function invalidModuleProvider(): array
    {
        return [
            'empty'             => ['', 'Module name is empty'],
            'null'              => [NULL, 'Module name is empty'],
            'array'             => [['strongs'], 'Module name is empty'],
            'uppercase'         => ['Strongs', 'Module name contains invalid characters'],
            'hyphen'            => ['strongs-ru', 'Module name contains invalid characters'],
            'space'             => ['strongs ru', 'Module name contains invalid characters'],
            'sql'               => ['a`;drop', 'Module name contains invalid characters'],
            'leading digit'     => ['1strongs', 'Module name must start with at least two letters'],
            'one leading letter'=> ['s1', 'Module name must start with at least two letters'],
        ];
    }
}
