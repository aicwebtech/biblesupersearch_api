<?php

namespace Tests\Unit\Factories;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Factories\EngineFactory;

/**
 * BSS-290: EngineFactory maps a requested API version onto the engine class that serves it.
 * The mapping is string concatenation, so a version that reaches it unvalidated would name a
 * class that does not exist - ApiController::versionedAction() is what keeps that from
 * happening, and the pairing of the two is what these tests pin.
 *
 * Only the pure class-name resolution is exercised here; constructing an engine needs the
 * database (see tests/Feature/Controllers/ApiVersionRoutingTest.php).
 */
class EngineFactoryTest extends TestCase
{
    #[DataProvider('classNameDataProvider')]
    public function testGetClassName($version, string $expected): void
    {
        $this->assertSame($expected, EngineFactory::getClassName($version));
    }

    public static function classNameDataProvider(): array
    {
        return [
            'v2'                 => [2,     'App\Engines\EngineV2'],
            'v3'                 => [3,     'App\Engines\EngineV3'],
            'numeric string'     => ['3',   'App\Engines\EngineV3'],
            'NULL uses default'  => [null,  'App\Engines\EngineV2'],
        ];
    }

    /**
     * The no-argument call is the one every internal caller makes; it must not drift onto a
     * newer engine without an explicit decision, since v3 changes the shape of the response.
     */
    public function testGetClassNameDefaultsToTheV2Engine(): void
    {
        $this->assertSame('App\Engines\EngineV2', EngineFactory::getClassName());
    }

    /**
     * Every version the application advertises must resolve to a class that actually exists,
     * otherwise the route 404 check in ApiController lets through a request that then fatals.
     */
    public function testEveryAdvertisedApiVersionResolvesToARealClass(): void
    {
        // config() is unavailable in a unit test; the advertised list is asserted against
        // config/app.php in the feature test, so the versions are named literally here.
        foreach ([2, 3] as $version) {
            $class = EngineFactory::getClassName($version);

            $this->assertTrue(class_exists($class), $class . ' does not exist');
            $this->assertTrue(is_subclass_of($class, 'App\Engine'), $class . ' does not extend App\Engine');
        }
    }

    public function testAnUnknownVersionResolvesToAClassThatDoesNotExist(): void
    {
        // The factory does not validate - it is the caller's job to reject the version first.
        $this->assertFalse(class_exists(EngineFactory::getClassName(4242)));
    }

    /**
     * Every method declares what it hands back.
     *
     * A factory whose return type is implicit is the one place a caller cannot tell an engine
     * from a class name without reading the body, and the three instance methods route through
     * Traits\Singleton, which can swap in a premium subclass. Asserted over the class rather
     * than method by method, so a method added later is covered without anyone remembering to.
     */
    public function testEveryPublicMethodDeclaresAReturnType(): void
    {
        $reflection = new \ReflectionClass(EngineFactory::class);
        $methods    = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $this->assertNotEmpty($methods);

        foreach($methods as $method) {
            $this->assertTrue($method->hasReturnType(), $method->getName() . '() has no return type');
        }
    }

    /** And the two that are load-bearing are the types the callers rely on. */
    public function testTheDeclaredReturnTypesAreTheOnesCallersRelyOn(): void
    {
        $reflection = new \ReflectionClass(EngineFactory::class);

        $this->assertSame('string', (string) $reflection->getMethod('getClassName')->getReturnType());

        foreach(['getNewEngine', 'getEngineInstance', 'getFreshEngineInstance'] as $name) {
            $this->assertSame(
                'App\\Engine',
                (string) $reflection->getMethod($name)->getReturnType(),
                $name
            );
        }
    }
}
