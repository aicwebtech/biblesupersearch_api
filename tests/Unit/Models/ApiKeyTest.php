<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\ApiAccessLevel;
use App\Models\ApiKey;

/**
 * Covers the ApiKey access-limit rules, which decide whether a request is served, throttled,
 * or refused outright.
 *
 * Every case here resolves from attributes already on the key, so no query is made: a key
 * with FULL access short-circuits, and a key carrying its own limit never consults its
 * access level. The relation and config fallbacks are feature tests.
 */
class ApiKeyTest extends TestCase
{
    /**
     * @param array<string, mixed> $attributes
     */
    private function key(array $attributes): ApiKey
    {
        $key = new ApiKey();

        foreach ($attributes as $name => $value) {
            $key->{$name} = $value;
        }

        return $key;
    }

    public function testANewKeyStartsAtBasicAccess(): void
    {
        $this->assertSame(ApiAccessLevel::BASIC, (new ApiKey())->access_level_id);
    }

    public function testSoftDeletesAreEnabled(): void
    {
        $this->assertContains(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses(ApiKey::class)
        );
    }

    /**
     * A FULL key is unlimited, expressed as a limit of 0, and must not fall through to the
     * access level or the configured default.
     */
    public function testFullAccessMeansNoLimit(): void
    {
        $key = $this->key(['access_level_id' => ApiAccessLevel::FULL]);

        $this->assertSame(0, $key->getAccessLimit());
        $this->assertTrue($key->hasUnlimitedAccess());
        $this->assertFalse($key->isAccessRevoked());
    }

    public function testAKeyWithItsOwnLimitUsesIt(): void
    {
        $key = $this->key(['access_level_id' => ApiAccessLevel::KEYED, 'limit' => 500]);

        $this->assertSame(500, $key->getAccessLimit());
    }

    public function testAKeyWithItsOwnLimitIsNotUnlimited(): void
    {
        $key = $this->key(['access_level_id' => ApiAccessLevel::KEYED, 'limit' => 500]);

        $this->assertFalse($key->hasUnlimitedAccess());
        $this->assertFalse($key->isAccessRevoked());
    }

    /**
     * NONE is the level delete() assigns, so a soft-deleted key is refused regardless of any
     * limit still stored against it.
     */
    public function testNoneAccessIsRevoked(): void
    {
        $key = $this->key(['access_level_id' => ApiAccessLevel::NONE, 'limit' => 500]);

        $this->assertTrue($key->isAccessRevoked());
        $this->assertFalse($key->hasUnlimitedAccess());
    }

    /**
     * A negative limit is the sentinel for revoked access - distinct from 0, which means
     * unlimited.
     */
    public function testANegativeLimitRevokesAccess(): void
    {
        $key = $this->key(['access_level_id' => ApiAccessLevel::KEYED, 'limit' => -1]);

        $this->assertTrue($key->isAccessRevoked());
        $this->assertFalse($key->hasUnlimitedAccess());
    }

    public function testAZeroLimitIsUnlimitedRatherThanRevoked(): void
    {
        $key = $this->key(['access_level_id' => ApiAccessLevel::KEYED, 'limit' => 0]);

        $this->assertFalse($key->isAccessRevoked());
        $this->assertTrue($key->hasUnlimitedAccess());
    }

    public function testIsLimitReachedIsFalseWhenTheKeyIsUnlimited(): void
    {
        $key = $this->key(['access_level_id' => ApiAccessLevel::FULL]);

        $this->assertFalse($key->isLimitReached());
    }

    /**
     * Generated keys are the public credential, so their shape is a contract: a TrU prefix
     * and a fixed overall length.
     */
    public function testGeneratedHashHasTheExpectedShape(): void
    {
        $method = new \ReflectionMethod(ApiKey::class, 'generateHashHelper');

        $hash = $method->invoke(null);

        $this->assertStringStartsWith('TrU', $hash);
        $this->assertSame(37, strlen($hash));
        $this->assertMatchesRegularExpression('/^TrU[A-Za-z0-9]+$/', $hash);
    }

    public function testGeneratedHashesDiffer(): void
    {
        $method = new \ReflectionMethod(ApiKey::class, 'generateHashHelper');

        $hashes = [];

        for ($i = 0; $i < 25; $i++) {
            $hashes[] = $method->invoke(null);
        }

        $this->assertCount(25, array_unique($hashes), 'generated key hashes must not collide');
    }
}
