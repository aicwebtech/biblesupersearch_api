<?php

namespace Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Console\Kernel;
use Illuminate\Console\Command;

/**
 * A contract test over every command listed in App\Console\Kernel::$commands.
 *
 * These commands install, import and migrate, so none of them are executed here. Constructing
 * one only parses its signature - no container, no database - which is enough to pin the
 * declarations Artisan relies on to expose it.
 */
class CommandRegistryTest extends TestCase
{
    /** Short class name, without relying on Laravel's helpers - no application is booted here. */
    private static function shortName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    /**
     * @return array<class-string<Command>>
     */
    private static function registeredCommands(): array
    {
        return (new \ReflectionClass(Kernel::class))->getDefaultProperties()['commands'] ?? [];
    }

    /**
     * @return array<string, array{class-string<Command>}>
     */
    public static function registeredCommandProvider(): array
    {
        $cases = [];

        foreach (self::registeredCommands() as $class) {
            $cases[self::shortName($class)] = [$class];
        }

        return $cases;
    }

    #[DataProvider('registeredCommandProvider')]
    public function testEveryRegisteredCommandClassExists(string $class): void
    {
        $this->assertTrue(class_exists($class), "{$class} is registered but cannot be loaded");
    }

    #[DataProvider('registeredCommandProvider')]
    public function testEveryRegisteredCommandIsAnArtisanCommand(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, Command::class));
    }

    /**
     * A command with no name cannot be invoked, and Artisan throws while building its list.
     */
    #[DataProvider('registeredCommandProvider')]
    public function testEveryRegisteredCommandHasAName(string $class): void
    {
        $command = new $class();

        $this->assertNotEmpty($command->getName(), "{$class} should declare a signature");
    }

    /**
     * The description is what `php artisan list` prints; an undescribed command is invisible
     * in practice.
     */
    #[DataProvider('registeredCommandProvider')]
    public function testEveryRegisteredCommandIsDescribed(string $class): void
    {
        $command = new $class();

        $this->assertNotEmpty($command->getDescription(), "{$class} should declare a description");
    }

    /**
     * Two commands sharing a name would silently shadow one another in Artisan.
     */
    public function testCommandNamesAreUnique(): void
    {
        $names = [];

        foreach (self::registeredCommands() as $class) {
            $names[] = (new $class())->getName();
        }

        $this->assertSame(array_unique($names), $names, 'registered command names must not collide');
    }

    /**
     * Commands extending BibleAbstract opt into a shared set of Bible-selection options via
     * $append_signature. Those three options are the whole interface for picking what a
     * destructive command acts on, so losing one would change every such command at once.
     */
    public function testBibleCommandsShareTheModuleSelectionOptions(): void
    {
        $checked = 0;

        foreach (self::registeredCommands() as $class) {
            if (!is_subclass_of($class, \App\Console\Commands\BibleAbstract::class)) {
                continue;
            }

            $command = new $class();

            $appends = (new \ReflectionProperty($class, 'append_signature'))->getValue($command);

            if (!$appends) {
                continue;
            }

            $definition = $command->getDefinition();

            $this->assertTrue($definition->hasOption('module'), "{$class} should accept --module");
            $this->assertTrue($definition->hasOption('all'), "{$class} should accept --all");
            $this->assertTrue($definition->hasOption('list'), "{$class} should accept --list");

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'expected at least one BibleAbstract command to check');
    }

    /**
     * ListBibles opts out of the shared options - it takes no arguments at all.
     */
    public function testListBiblesOptsOutOfTheSharedOptions(): void
    {
        $command = new \App\Console\Commands\ListBibles();

        $this->assertSame('bible:list', $command->getName());
        $this->assertFalse($command->getDefinition()->hasOption('module'));
    }

    /**
     * Every command file under app/Console/Commands should either be registered or be an
     * abstract base. An unregistered concrete command is dead code that Artisan never exposes.
     *
     * This is reported rather than asserted strictly: the Kernel deliberately comments out
     * several dev-only commands, so the list below records the current, intended state.
     */
    public function testUnregisteredCommandsAreOnlyTheKnownDevAndAbstractOnes(): void
    {
        $registered = array_map([self::class, 'shortName'], self::registeredCommands());

        $onDisk = [];

        foreach (glob(__DIR__ . '/../../../app/Console/Commands/*.php') ?: [] as $file) {
            $onDisk[] = basename($file, '.php');
        }

        $unregistered = array_values(array_diff($onDisk, $registered));
        sort($unregistered);

        $expected = [
            'BibleAbstract',        // abstract base
            'BibleOfficial',        // dev tool, commented out in the Kernel
            'DbTemplateSquash',     // commented out in the Kernel
            'ImportBible',          // abstract base for the ImportBible* commands
            'ImportBibleCustom',    // dev tool, commented out in the Kernel
            'ImportBibleEvening',   // obsolete, commented out in the Kernel
            'MigrationCustom',      // commented out in the Kernel
        ];

        $this->assertSame($expected, $unregistered);
    }

    /**
     * The two abstract bases must stay abstract - registering one directly would expose a
     * command with no handle() implementation.
     */
    public function testTheCommandBasesAreAbstract(): void
    {
        $this->assertTrue((new \ReflectionClass(\App\Console\Commands\BibleAbstract::class))->isAbstract());
        $this->assertTrue((new \ReflectionClass(\App\Console\Commands\ImportBible::class))->isAbstract());
    }
}
