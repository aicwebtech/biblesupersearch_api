<?php

namespace Tests\Feature;

use App\BibleSuperSearch;
use Tests\TestCase;

/**
 * App\BibleSuperSearch is the documented entry point for a third-party PHP application
 * running on the same server: it boots the framework itself and forwards actions to the
 * Engine. Nothing in the suite exercised it before.
 *
 * Constructing it inside a feature test is safe - _makeApp() sees LARAVEL_START already
 * defined, reuses the running application, and the kernel bootstrap is a no-op once the
 * application has been bootstrapped.
 */
class BibleSuperSearchTest extends TestCase
{
    public function testItConstructsAgainstTheRunningApplication(): void
    {
        $this->assertInstanceOf(BibleSuperSearch::class, new BibleSuperSearch());
    }

    /**
     * The action name is studly-cased onto an Engine method, so 'query' reaches
     * Engine::actionQuery.
     */
    public function testQueryReturnsResultsForAPassage(): void
    {
        $results = (new BibleSuperSearch())->actionQuery(['bible' => 'kjv', 'reference' => 'John 3:16']);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function testDoActionForwardsToTheEngine(): void
    {
        $results = (new BibleSuperSearch())->doAction('books', ['language' => 'en']);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    /**
     * An unknown action must fail loudly rather than returning an empty result that the
     * caller would mistake for "no matches".
     */
    public function testAnUnknownActionThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Action does not exist:');

        (new BibleSuperSearch())->doAction('no_such_action', []);
    }

    public function testMetadataIsAvailableAfterAnAction(): void
    {
        $bss = new BibleSuperSearch();
        $bss->actionQuery(['bible' => 'kjv', 'reference' => 'John 3:16']);

        $this->assertNotNull($bss->getActionMetadata());
    }

    /**
     * Errors are opt-in on the metadata, so a caller that asks for them gets the key.
     */
    public function testMetadataCanIncludeErrors(): void
    {
        $bss = new BibleSuperSearch();
        $bss->actionQuery(['bible' => 'kjv', 'reference' => 'John 3:16']);

        $metadata = $bss->getActionMetadata(true);

        $this->assertNotNull($metadata);
    }
}
