<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Engine;
use App\RenderManager;
use App\User;

/**
 * The render limit caps how many Bibles may be rendered synchronously in one
 * request. `bypass_limit` used to be read straight from request input, so an
 * unauthenticated caller could switch that control off -- and because it was a
 * bare truthy check, even the string "false" enabled it.
 *
 * It cannot be removed outright: the download widget ships it as a hidden input
 * that JavaScript zeroes on load (public/widgets/download/download.php), and
 * with JavaScript off there is no client to drive the multi-request render
 * flow. So the request may still ask, but authorisation is decided server side
 * -- only an administrator is granted it, and only for a genuinely true value.
 */
class RenderBypassLimitTest extends TestCase
{
    /**
     * @param  array  $input
     * @return bool
     */
    protected function bypassGranted(array $input): bool
    {
        // Reflection ignores visibility from PHP 8.1, and setAccessible() is
        // deprecated from 8.5, so it is deliberately not called here.
        $method = new \ReflectionMethod(Engine::class, '_bypassRenderLimitRequested');

        return $method->invoke(new Engine(), $input);
    }

    /**
     * The administrator the installer creates; the gate is access_level >= 100.
     *
     * @return \App\User
     */
    protected function adminUser(): User
    {
        $User = User::where('access_level', '>=', 100)->first();

        $this->assertNotNull($User, 'an administrator is required for this test');

        return $User;
    }

    /**
     * An unauthenticated caller is refused however the flag is spelled --
     * including "false", which the old bare truthy check treated as ON.
     */
    public function testAnonymousCallerIsNeverGrantedTheBypass(): void
    {
        $this->assertFalse($this->bypassGranted(['bypass_limit' => 1]));
        $this->assertFalse($this->bypassGranted(['bypass_limit' => '1']));
        $this->assertFalse($this->bypassGranted(['bypass_limit' => 'true']));
        $this->assertFalse($this->bypassGranted(['bypass_limit' => 'false']));
    }

    /**
     * A signed-in non-administrator is no better placed than an anonymous one.
     */
    public function testNonAdminUserIsNotGrantedTheBypass(): void
    {
        $User = new User();
        $User->access_level = 1;

        $this->be($User);

        $this->assertFalse($this->bypassGranted(['bypass_limit' => 1]));
    }

    /**
     * The capability the no-JavaScript widget depends on still exists.
     */
    public function testAdminIsGrantedTheBypass(): void
    {
        $this->be($this->adminUser());

        $this->assertTrue($this->bypassGranted(['bypass_limit' => 1]));
        $this->assertTrue($this->bypassGranted(['bypass_limit' => '1']));
        $this->assertTrue($this->bypassGranted(['bypass_limit' => 'true']));
    }

    /**
     * Parsed strictly, so the values that mean "off" are honoured as off even
     * for an administrator. This is what the old bare truthy check got wrong.
     */
    public function testFalseyValuesAreOffEvenForAnAdmin(): void
    {
        $this->be($this->adminUser());

        $this->assertFalse($this->bypassGranted(['bypass_limit' => 'false']));
        $this->assertFalse($this->bypassGranted(['bypass_limit' => '0']));
        $this->assertFalse($this->bypassGranted(['bypass_limit' => 0]));
        $this->assertFalse($this->bypassGranted(['bypass_limit' => '']));
    }

    /**
     * The widget zeroes the input when JavaScript runs, and other clients omit
     * it entirely; neither may switch the control on.
     */
    public function testAbsentFlagIsOff(): void
    {
        $this->be($this->adminUser());

        $this->assertFalse($this->bypassGranted([]));
        $this->assertFalse($this->bypassGranted(['bible' => 'kjv']));
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
     * Supplying the parameter to an action that does not render is inert.
     *
     * actionRenderNeeded() also runs cleanUpTempFiles(), which deletes any
     * rendering already on disk -- so the first call of a run answers about a
     * different state than the rest. The warm-up call settles that, leaving the
     * three compared calls genuinely like for like.
     */
    public function testSuppliedBypassLimitChangesNothingForRenderNeeded(): void
    {
        $base = ['bible' => 'kjv', 'format' => 'text'];

        (new Engine())->actionRenderNeeded($base); // warm-up, see above

        $without = (new Engine())->actionRenderNeeded($base);
        $with_true = (new Engine())->actionRenderNeeded($base + ['bypass_limit' => 1]);
        $with_false = (new Engine())->actionRenderNeeded($base + ['bypass_limit' => 'false']);

        $this->assertEquals($without, $with_true, 'bypass_limit=1 must not change the outcome');
        $this->assertEquals($without, $with_false, 'bypass_limit="false" must not change the outcome');
    }
}
