<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\ApiAccessManager;
use App\Models\ApiKey;
use App\Models\ApiAccessLevel;

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
     * lookUpHelper() documents NULL for an unknown *or revoked* key, but the
     * revoked branch handed the ApiKey back: ApiAccess::handle happens to
     * re-check isAccessRevoked(), so a caller that trusted the documented
     * contract and skipped that check would have granted the revoked key access.
     *
     * The key row is a purpose-built fixture, removed in the finally below.
     */
    public function testLookUpByInputReturnsNullForRevokedKey(): void
    {
        config(['app.experimental' => true]);

        $key = ApiKey::generateKeyHash();

        $Key = new ApiKey;
        $Key->key = $key;
        $Key->access_level_id = ApiAccessLevel::NONE;
        // api_keys.user_id is NOT NULL with no default - see KeyAccessTest::_fakeKey()
        $Key->user_id = 0;
        $Key->save();

        try {
            $this->assertTrue(ApiKey::findByKey($key)->isAccessRevoked(), 'Precondition: the key is revoked');

            $this->assertNull(ApiAccessManager::lookUpByInput(['key' => $key]));
        }
        finally {
            ApiKey::withTrashed()->where('key', $key)->forceDelete();
        }
    }

    /**
     * A revoked key must not silently fall through to the keyless IP record
     * either - that would hand back working access for a key that was cut off.
     */
    public function testRevokedKeyDoesNotFallBackToKeylessAccess(): void
    {
        config(['app.experimental' => true]);

        $key = ApiKey::generateKeyHash();

        $Key = new ApiKey;
        $Key->key = $key;
        $Key->access_level_id = ApiAccessLevel::NONE;
        $Key->user_id = 0;
        $Key->save();

        try {
            $response = $this->getJson('/api/v2/query?bible=kjv&reference=John+3:16&key=' . $key);

            $response->assertStatus(403);
        }
        finally {
            ApiKey::withTrashed()->where('key', $key)->forceDelete();
        }
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
