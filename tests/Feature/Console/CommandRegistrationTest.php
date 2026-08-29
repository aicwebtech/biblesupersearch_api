<?php

namespace Tests\Feature\Console;

use App\Console\Kernel;
use Tests\TestCase;

/**
 * The unit counterpart (Tests\Unit\Console\CommandRegistryTest) checks what the Kernel
 * declares. This checks what Artisan actually ends up with once the application has booted -
 * a command can be declared correctly and still fail to resolve, for instance if its
 * constructor needs something the container cannot provide.
 *
 * No command is executed: these install, import and migrate.
 */
class CommandRegistrationTest extends TestCase
{
    /**
     * @return array<class-string>
     */
    private function registeredCommands(): array
    {
        return (new \ReflectionClass(Kernel::class))->getDefaultProperties()['commands'] ?? [];
    }

    public function testEveryKernelCommandResolvesInArtisan(): void
    {
        $artisan = \Artisan::all();

        foreach ($this->registeredCommands() as $class) {
            $name = (new $class())->getName();

            $this->assertArrayHasKey($name, $artisan, "{$class} ({$name}) should be available to Artisan");
            $this->assertInstanceOf($class, $artisan[$name]);
        }
    }

    /**
     * Names are the public interface of the CLI; this pins the ones documented for operators
     * so a signature change has to be deliberate.
     */
    public function testTheDocumentedCommandNamesArePresent(): void
    {
        $artisan = \Artisan::all();

        foreach (['app:install', 'app:install-testing', 'bible:list', 'bible:render', 'user:create'] as $name) {
            $this->assertArrayHasKey($name, $artisan);
        }
    }

    /**
     * Artisan builds every registered command when it lists them, so this also proves no
     * command's constructor throws once the container is available.
     */
    public function testArtisanCanListEveryCommandWithoutThrowing(): void
    {
        $artisan = \Artisan::all();

        $this->assertNotEmpty($artisan);

        foreach ($this->registeredCommands() as $class) {
            $name = (new $class())->getName();

            $this->assertNotEmpty($artisan[$name]->getDescription(), "{$name} should describe itself to Artisan");
        }
    }
}
