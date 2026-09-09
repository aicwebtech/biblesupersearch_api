<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\User;
use App\Helpers;
use App\Models\Bible;

/**
 * The Bible edit form validates the module name client-side before submitting.
 * Its reserved-word list is supplied by the server so it cannot drift from
 * Bible::validateModule().
 */
class ModuleReservedWordBootstrapTest extends TestCase
{
    protected function admin(): User
    {
        $User = User::find(1);
        $this->assertGreaterThanOrEqual(100, $User->access_level);

        return $User;
    }

    public function testBootstrapCarriesReservedWordList(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/bibles');

        $response->assertStatus(200);

        // Present in the bootstrap payload the form reads.
        $response->assertSee('php_reserved_words', false);

        foreach(['"for"', '"new"', '"as"'] as $word) {
            $response->assertSee($word, false);
        }
    }

    /**
     * Whatever the client list contains, the server must reject the same words.
     */
    public function testEveryPublishedWordIsRejectedByTheBackend(): void
    {
        foreach(Helpers::phpReservedWords() as $word) {
            // validateModule also requires two leading letters, so only check
            // words that would otherwise pass the format rules.
            if(!preg_match('/^[a-z]{2}[a-z0-9_]*$/', $word)) {
                continue;
            }

            $this->assertFalse(
                Bible::validateModule($word),
                'Reserved word should be rejected by the backend: ' . $word
            );
        }
    }
}
