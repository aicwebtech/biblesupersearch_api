<?php

namespace Tests\Unit\Renderers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\RenderManager;
use App\Renderers\Extras\ExtrasAbstract;
use App\Renderers\RenderAbstract;

/**
 * Every download format is a class listed in RenderManager::$register. Each one carries
 * static declarations the render pipeline depends on, and none of them were covered before:
 * the registry itself was only ever exercised through a full render.
 *
 * All of this reads static properties, so it resolves with no application booted.
 */
class RendererRegistryTest extends TestCase
{
    /**
     * @return array<string, array{string, class-string<RenderAbstract>}>
     */
    public static function registeredRendererProvider(): array
    {
        $cases = [];

        foreach (RenderManager::$register as $format => $class) {
            $cases[$format] = [$format, $class];
        }

        return $cases;
    }

    private function staticValue(string $class, string $property): mixed
    {
        return (new \ReflectionProperty($class, $property))->getValue();
    }

    #[DataProvider('registeredRendererProvider')]
    public function testEveryRegisteredRendererClassExists(string $format, string $class): void
    {
        $this->assertTrue(class_exists($class), "renderer for format '{$format}' should be loadable");
    }

    #[DataProvider('registeredRendererProvider')]
    public function testEveryRegisteredRendererDescendsFromRenderAbstract(string $format, string $class): void
    {
        $this->assertTrue(is_subclass_of($class, RenderAbstract::class));
    }

    /**
     * $name and $description are shown to the user in the download picker, so an unnamed
     * format would appear as a blank option.
     */
    #[DataProvider('registeredRendererProvider')]
    public function testEveryRegisteredRendererIsNamed(string $format, string $class): void
    {
        $this->assertNotEmpty($class::$name, "format '{$format}' should declare a name");
        $this->assertIsString($class::$name);
    }

    /**
     * The render version is what invalidates already-rendered files: it has to be present
     * and numeric, or a changed renderer would keep serving stale downloads.
     */
    #[DataProvider('registeredRendererProvider')]
    public function testEveryRegisteredRendererDeclaresANumericRenderVersion(string $format, string $class): void
    {
        $version = $this->staticValue($class, 'render_version');

        $this->assertIsNumeric($version, "format '{$format}' should declare a numeric render_version");
        $this->assertGreaterThan(0, $version);
    }

    /**
     * The estimates drive the decision to fork a detached render process, so a zero or
     * missing estimate would let a long render block the request.
     */
    #[DataProvider('registeredRendererProvider')]
    public function testEveryRegisteredRendererEstimatesTimeAndSize(string $format, string $class): void
    {
        $this->assertGreaterThan(0, $this->staticValue($class, 'render_est_time'));
        $this->assertGreaterThan(0, $this->staticValue($class, 'render_est_size'));
    }

    /**
     * The limit is either a Bible count or TRUE meaning "never needs a detached process".
     */
    #[DataProvider('registeredRendererProvider')]
    public function testRenderBiblesLimitIsACountOrTrue(string $format, string $class): void
    {
        $limit = $this->staticValue($class, 'render_bibles_limit');

        $this->assertTrue($limit === true || (is_numeric($limit) && $limit > 0));
    }

    /**
     * An extras class is optional, but when declared it must actually be an extras renderer -
     * ExtrasAbstract is instantiated directly by the render pipeline.
     */
    #[DataProvider('registeredRendererProvider')]
    public function testExtrasClassIsEitherAbsentOrAnExtrasRenderer(string $format, string $class): void
    {
        $extras = $class::$extras_class;

        if ($extras === null) {
            $this->assertNull($extras);

            return;
        }

        $this->assertTrue(class_exists($extras), "extras class for '{$format}' should be loadable");
        $this->assertTrue(
            $extras === ExtrasAbstract::class || is_subclass_of($extras, ExtrasAbstract::class),
            "extras class for '{$format}' should extend ExtrasAbstract"
        );
    }

    /**
     * The extension names the file the user downloads, and the render pipeline refuses to
     * construct a renderer without one.
     */
    #[DataProvider('registeredRendererProvider')]
    public function testEveryRegisteredRendererDeclaresAFileExtension(string $format, string $class): void
    {
        $extension = (new \ReflectionClass($class))->getDefaultProperties()['file_extension'] ?? null;

        $this->assertNotEmpty($extension, "format '{$format}' should declare a file extension");
        $this->assertIsString($extension);
    }

    // -----------------------------------------------------------------------
    // The registry and the user-facing grouping must agree
    // -----------------------------------------------------------------------

    /**
     * $format_kinds is what the download UI lists. A format named there but missing from
     * $register would be offered and then fail to resolve a renderer.
     */
    public function testEveryGroupedFormatIsRegistered(): void
    {
        foreach (RenderManager::$format_kinds as $kind => $definition) {
            foreach ($definition['formats'] as $format) {
                $this->assertArrayHasKey(
                    $format,
                    RenderManager::$register,
                    "format '{$format}' listed under '{$kind}' should be registered"
                );
            }
        }
    }

    public function testEveryFormatKindIsNamedAndHasFormats(): void
    {
        foreach (RenderManager::$format_kinds as $kind => $definition) {
            $this->assertNotEmpty($definition['name'], "kind '{$kind}' should be named");
            $this->assertNotEmpty($definition['formats'], "kind '{$kind}' should list formats");
        }
    }

    /**
     * The Json renderer publishes the Json extras alongside the Bible, which is the only
     * wiring between the two hierarchies.
     */
    public function testJsonRendererPublishesTheJsonExtras(): void
    {
        $this->assertSame(\App\Renderers\Extras\Json::class, \App\Renderers\Json::$extras_class);
    }
}
