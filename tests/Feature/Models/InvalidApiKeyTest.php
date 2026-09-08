<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\ApiAccessManager;

/**
 * lookUpHelper() declared a non-nullable AccessLogInterface return type but
 * returned FALSE for an unknown or revoked key, which raised a TypeError and
 * surfaced as a 500 instead of the intended 403.
 *
 * These tests force app.experimental on rather than skipping when it is off,
 * because the faulty branch is only reachable with it enabled.
 */
class InvalidApiKeyTest extends TestCase
{
    protected $lift_daily_access_limit = FALSE;

    public function testLookUpByInputReturnsNullForUnknownKey(): void
    {
        config(['app.experimental' => true]);

        $Access = ApiAccessManager::lookUpByInput(['key' => 'not-a-real-key']);

        $this->assertNull($Access);
    }

    /**
     * Without the key parameter the IP-based record is still returned, so the
     * nullable return type has not broken keyless access.
     */
    public function testLookUpByInputStillReturnsRecordWithoutKey(): void
    {
        config(['app.experimental' => true]);

        $Access = ApiAccessManager::lookUpByInput([]);

        $this->assertNotNull($Access);
    }

    /**
     * The end-to-end contract: a bogus key is refused with 403, not a 500.
     */
    public function testUnknownKeyIsRefusedWithForbiddenNotServerError(): void
    {
        config(['app.experimental' => true]);

        $response = $this->getJson('/api/v2/query?bible=kjv&reference=John+3:16&key=not-a-real-key');

        $this->assertNotSame(500, $response->getStatusCode(), 'Unknown key raised a server error');
        $response->assertStatus(403);
    }
}
