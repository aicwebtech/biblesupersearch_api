<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\User;
use App\Models\Feature;

class FeatureControllerTest extends TestCase
{
    protected $User = NULL;

    public function setUp(): void
    {
        parent::setUp();
        
        // Create a test user with admin privileges
        $this->User = new User;
        $this->User->name = 'Test Admin';
        $this->User->access_level = 100; // Admin access level
    }

    public function test_index_syncs_features()
    {
        if (!$this->User || $this->User->access_level < 100) {
            $this->markTestSkipped('Admin user (id=1) not available for this environment.');
        }

        // Request index page (which calls syncFeatures)
        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->get('/admin/features');

        $response->assertStatus(200);

        // Check that features were synced to database
        $this->assertGreaterThan(0, Feature::count());
        
        // Should have 1 cross_references (null language) + 3 strongs (en, ru, es)
        $this->assertEquals(4, Feature::count());
    }

    public function test_grid_returns_feature_rows()
    {
        if (!$this->User || $this->User->access_level < 100) {
            $this->markTestSkipped('Admin user (id=1) not available for this environment.');
        }

        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->getJson('/admin/features/grid?' . http_build_query([
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
            ]));

        $response->assertStatus(200);
        $this->assertEquals(4, $response['records']);
        $this->assertEquals(4, count($response['rows']));
    }

    public function test_grid_includes_feature_definitions_data()
    {
        if (!$this->User || $this->User->access_level < 100) {
            $this->markTestSkipped('Admin user (id=1) not available for this environment.');
        }

        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->getJson('/admin/features/grid?' . http_build_query([
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
            ]));

        $response->assertStatus(200);

        $rows = $response['rows'];
        $this->assertNotEmpty($rows);
        
        // Check that definition data is included
        foreach ($rows as $row) {
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('description', $row);
            $this->assertArrayHasKey('language_name', $row);
            $this->assertArrayHasKey('code', $row);
            $this->assertArrayHasKey('installed', $row);
            $this->assertArrayHasKey('enabled', $row);
        }
    }

    public function test_grid_shows_cross_references_with_null_language()
    {
        if (!$this->User || $this->User->access_level < 100) {
            $this->markTestSkipped('Admin user (id=1) not available for this environment.');
        }

        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->getJson('/admin/features/grid?' . http_build_query([
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
            ]));

        $response->assertStatus(200);

        $crossRefRow = collect($response['rows'])->firstWhere('identifier', 'cross_references');
        
        $this->assertNotNull($crossRefRow);
        $this->assertNull($crossRefRow['language']);
        $this->assertEquals('—', $crossRefRow['language_name']);
        $this->assertEquals('Cross References', $crossRefRow['name']);
    }

    public function test_grid_shows_strongs_with_separate_rows_per_language()
    {
        if (!$this->User || $this->User->access_level < 100) {
            $this->markTestSkipped('Admin user (id=1) not available for this environment.');
        }

        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->getJson('/admin/features/grid?' . http_build_query([
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
            ]));

        $response->assertStatus(200);

        $strongsRows = collect($response['rows'])->where('identifier', 'strongs')->values();
        
        $this->assertEquals(3, count($strongsRows));
        
        $languages = $strongsRows->pluck('language')->sort()->values();
        $this->assertEquals(['en', 'es', 'ru'], $languages->toArray());
    }

    public function test_sync_does_not_duplicate_existing_rows()
    {
        if (!$this->User || $this->User->access_level < 100) {
            $this->markTestSkipped('Admin user (id=1) not available for this environment.');
        }

        Feature::syncFeatures();
        $initialCount = Feature::count();

        // Call sync again
        Feature::syncFeatures();

        $this->assertEquals($initialCount, Feature::count());
    }

    public function test_grid_search_by_identifier()
    {
        if (!$this->User || $this->User->access_level < 100) {
            $this->markTestSkipped('Admin user (id=1) not available for this environment.');
        }

        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->getJson('/admin/features/grid?' . http_build_query([
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
                'name' => 'strongs',
            ]));

        $response->assertStatus(200);

        $this->assertEquals(3, $response['records']);
    }

    public function test_grid_search_by_language()
    {
        if (!$this->User || $this->User->access_level < 100) {
            $this->markTestSkipped('Admin user (id=1) not available for this environment.');
        }

        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->getJson('/admin/features/grid?' . http_build_query([
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
                'language' => 'en',
            ]));

        $response->assertStatus(200);

        $this->assertEquals(1, $response['records']);
        $this->assertEquals('en', $response['rows'][0]['language']);
    }
}
