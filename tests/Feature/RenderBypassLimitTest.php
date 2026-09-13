<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Engine;
use App\RenderManager;

/**
 * The render limit caps how many Bibles may be rendered synchronously in one
 * request. `bypass_limit` used to be read straight from request input, so an
 * unauthenticated caller could switch that control off -- and because it was a
 * bare truthy check, even the string "false" enabled it.
 *
 * The flag is retained on RenderManager for trusted internal callers.
 */
class RenderBypassLimitTest extends TestCase
{
    /**
     * The API no longer reads the parameter at all.
     */
    public function testBypassLimitIsNotReadFromRequestInput(): void
    {
        $source = file_get_contents(app_path('Engine.php'));

        $this->assertStringNotContainsString(
            "array_key_exists('bypass_limit'",
            $source,
            'Engine must not read bypass_limit from request input'
        );
    }

    /**
     * The internal capability is kept: both methods still accept the flag.
     */
    public function testRenderManagerStillAcceptsTheFlagInternally(): void
    {
        foreach(['render', 'download'] as $name) {
            $method = new \ReflectionMethod(RenderManager::class, $name);
            $names = array_map(fn($p) => $p->getName(), $method->getParameters());

            $this->assertContains(
                'bypass_render_limit',
                $names,
                RenderManager::class . '::' . $name . '() must keep the internal flag'
            );
        }
    }

    /**
     * Supplying the parameter through the API is inert: the response is
     * identical with and without it, including for the string "false", which
     * the old bare truthy check treated as ON.
     */
    public function testSuppliedBypassLimitChangesNothing(): void
    {
        $base = ['bible' => 'kjv', 'format' => 'text'];

        $without = (new Engine())->actionRenderNeeded($base);
        $with_true = (new Engine())->actionRenderNeeded($base + ['bypass_limit' => 1]);
        $with_false = (new Engine())->actionRenderNeeded($base + ['bypass_limit' => 'false']);

        $this->assertEquals($without, $with_true, 'bypass_limit=1 must not change the outcome');
        $this->assertEquals($without, $with_false, 'bypass_limit="false" must not change the outcome');
    }
}
