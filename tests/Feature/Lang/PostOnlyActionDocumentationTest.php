<?php

namespace Tests\Feature\Lang;

use Tests\TestCase;
use App\Http\Controllers\ApiController;

/**
 * BSS-290: the actions that hand back a file are POST-only from API v3 on, and the parameter
 * docs are the only place a caller learns it.
 *
 * ApiController::genericAction() answers a GET with a bare 405 'Action requires POST method'
 * and nothing points at the version rule behind it, so a v2 client migrating by swapping the
 * URL prefix has no way to tell whether the action is gone, the install has downloads off, or
 * the method is wrong. Every other v3 behaviour change carries a note on its parameter page -
 * see HighlightTagDocumentationTest - and this asserts this one does too, and still names the
 * actions the controller actually gates.
 */
class PostOnlyActionDocumentationTest extends TestCase
{
    /** The rendered description names every action under the gate. */
    public function testTheDocumentedActionListMatchesTheEnforcedList(): void
    {
        $description = trans('api.download.description');

        $this->assertNotSame('api.download.description', $description);
        $this->assertNotEmpty(ApiController::POST_ONLY_ACTIONS);

        foreach(ApiController::POST_ONLY_ACTIONS as $action) {
            $this->assertMatchesRegularExpression('/\b' . preg_quote($action, '/') . '\b/', $description, $action);
        }
    }

    /** And says what a caller who gets it wrong is answered with. */
    public function testTheDescriptionNamesTheMethodAndTheStatus(): void
    {
        $description = trans('api.download.description');

        $this->assertStringContainsString('POST', $description);
        $this->assertStringContainsString('405', $description);
        $this->assertStringContainsString('GET', $description);
    }

    /**
     * And that the rule starts at v3, since the whole point of the note is the client that is
     * migrating - a reader who takes it for a blanket rule would break their working v2 calls.
     */
    public function testTheDescriptionSaysTheRuleStartsAtV3(): void
    {
        $description = trans('api.download.description');

        $this->assertStringContainsString('v3', $description);
        $this->assertStringContainsString('v2', $description);
    }

    /**
     * 'render' shares the gate but has no docs page of its own, so the download page is where a
     * caller has to be able to find it.
     */
    public function testTheDescriptionNamesRenderWhichHasNoPageOfItsOwn(): void
    {
        $this->assertContains('render', ApiController::POST_ONLY_ACTIONS);
        $this->assertStringContainsString('render', trans('api.download.description'));
    }
}
