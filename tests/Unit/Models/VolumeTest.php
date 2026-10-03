<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Models\Volume;
use App\Models\VolumeContent\ContentBase;
use App\Models\VolumeContent\ContentInterface;
use Illuminate\Database\Schema\Blueprint;
use App\Models\VolumeContent\StrongsContent;

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
        $this->assertSame(ContentBase::CONTENT_TABLE_PREFIX . StrongsContent::TYPE_TABLE_PREFIX . 'strongs_ru', $Content->getTable());
        $this->assertSame($Content, $Volume->content(), 'content() should reuse its instance');
    }

    public function testContentRequiresTypeAndModule(): void
    {
        $Volume = new Volume();
        $Volume->type = 'strongs';

        $this->expectException(\Exception::class);
        $Volume->content();
    }

    public function testContentTableNameNeedsAType(): void
    {
        $this->expectException(\LogicException::class);
        (new StrongsContent())->setModule('strongs_ru');
    }

    public function testContentTableNameLengthIncludesTheDbPrefix(): void
    {
        $module = str_repeat('a', 50); // the module max length
        $length = strlen(StrongsContent::getContentTableName('strongs', $module));
        $fits   = str_repeat('p', Volume::MAX_TABLE_NAME_LENGTH - $length);

        $this->assertFalse(Volume::contentTableNameTooLong('strongs', $module, $fits));
        $this->assertTrue(Volume::contentTableNameTooLong('strongs', $module, $fits . 'p'));
        $this->assertFalse(Volume::contentTableNameTooLong('not_a_type', $module, $fits . 'p'));
    }

    public function testEveryContentTableNameStartsWithTheContentPrefix(): void
    {
        foreach(array_keys(Volume::getTypes()) as $type) {
            $table = Volume::getContentClassName($type)::getContentTableName($type, 'mod');

            $this->assertStringStartsWith(ContentBase::CONTENT_TABLE_PREFIX, $table, $type);
            $this->assertStringEndsWith('_mod', $table, $type);
        }
    }

    public function testStrongsHasItsOwnShortTypePrefix(): void
    {
        $this->assertSame('stro_', StrongsContent::TYPE_TABLE_PREFIX);
        $this->assertNull(ContentBase::TYPE_TABLE_PREFIX);
    }

    public function testContentTableNameDefaultsToTheTypeWithoutATypePrefix(): void
    {
        $Content = new class extends ContentBase {
            protected function createSchema(Blueprint $table): void {}
        };

        $this->assertSame(ContentBase::CONTENT_TABLE_PREFIX . 'commentary_mod', $Content::getContentTableName('commentary', 'mod'));
    }

    /**
     * A new type without a short table prefix of its own would fail this, so it is caught when
     * the type is registered rather than when someone picks a long module name.
     */
    public function testEveryTypeFitsAMaxLengthModuleUnderTheDefaultDbPrefix(): void
    {
        foreach(array_keys(Volume::getTypes()) as $type) {
            $this->assertFalse(
                Volume::contentTableNameTooLong($type, str_repeat('a', 50), 'bss_'),
                $type . ': content table name exceeds ' . Volume::MAX_TABLE_NAME_LENGTH . ' characters for a 50 character module'
            );
        }
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
