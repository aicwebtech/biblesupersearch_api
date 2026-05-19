<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\User;
use App\Models\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FeatureControllerTest extends TestCase
{
    use RefreshDatabase;

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
        // Verify features table starts empty
        $this->assertEquals(0, Feature::count());

        // Request index page (which calls syncFeatures)
        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->get('/admin/features');

        // Check that features were synced to database
        $this->assertGreaterThan(0, Feature::count());
        
        // Should have 1 cross_references (null language) + 3 strongs (en, ru, es)
        $this->assertEquals(4, Feature::count());
    }

    public function test_grid_returns_feature_rows()
    {
        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->postJson('/admin/features/grid', [
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
            ]);

        $response->assertStatus(200);
        $this->assertEquals(4, $response['records']);
        $this->assertEquals(4, count($response['rows']));
    }

    public function test_grid_includes_feature_definitions_data()
    {
        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->postJson('/admin/features/grid', [
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
            ]);

        $rows = $response['rows'];
        
        // Check that definition data is included
        foreach ($rows as $row) {
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('description', $row);
            $this->assertArrayHasKey('language_name', $row);
            $this->assertArrayHasKey('installed', $row);
        }
    }

    public function test_grid_shows_cross_references_with_null_language()
    {
        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->postJson('/admin/features/grid', [
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
            ]);

        $crossRefRow = collect($response['rows'])->firstWhere('identifier', 'cross_references');
        
        $this->assertNotNull($crossRefRow);
        $this->assertNull($crossRefRow['language']);
        $this->assertEquals('—', $crossRefRow['language_name']);
        $this->assertEquals('Cross References', $crossRefRow['name']);
    }

    public function test_grid_shows_strongs_with_separate_rows_per_language()
    {
        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->postJson('/admin/features/grid', [
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
            ]);

        $strongsRows = collect($response['rows'])->where('identifier', 'strongs')->values();
        
        $this->assertEquals(3, count($strongsRows));
        
        $languages = $strongsRows->pluck('language')->sort()->values();
        $this->assertEquals(['en', 'es', 'ru'], $languages->toArray());
    }

    public function test_install_sets_installed_flag()
    {
        $Feature = Feature::create([
            'identifier' => 'test_feature',
            'language' => null,
            'installed' => false,
        ]);

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->postJson('/admin/features/install/' . $Feature->id);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $Feature->refresh();
        $this->assertTrue($Feature->installed);
    }

    public function test_uninstall_sets_installed_flag()
    {
        $Feature = Feature::create([
            'identifier' => 'test_feature',
            'language' => null,
            'installed' => true,
        ]);

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->postJson('/admin/features/uninstall/' . $Feature->id);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $Feature->refresh();
        $this->assertFalse($Feature->installed);
    }

    public function test_feature_identified_by_identifier_and_language()
    {
        // Create two strongs entries with different languages
        $Feature1 = Feature::create([
            'identifier' => 'strongs',
            'language' => 'en',
            'installed' => false,
        ]);

        $Feature2 = Feature::create([
            'identifier' => 'strongs',
            'language' => 'ru',
            'installed' => false,
        ]);

        // Install only Feature1
        $response1 = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->postJson('/admin/features/install/' . $Feature1->id);

        $response1->assertStatus(200);

        // Check that only Feature1 is installed
        $Feature1->refresh();
        $Feature2->refresh();
        
        $this->assertTrue($Feature1->installed);
        $this->assertFalse($Feature2->installed);
    }

    public function test_sync_does_not_duplicate_existing_rows()
    {
        Feature::syncFeatures();
        $initialCount = Feature::count();

        // Call sync again
        Feature::syncFeatures();

        $this->assertEqual($initialCount, Feature::count());
    }

    public function test_grid_search_by_identifier()
    {
        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->postJson('/admin/features/grid', [
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
                'name' => 'strongs',
            ]);

        $this->assertEquals(3, $response['records']);
    }

    public function test_grid_search_by_language()
    {
        Feature::syncFeatures();

        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->postJson('/admin/features/grid', [
                'rows' => 25,
                'page' => 1,
                'sidx' => 'id',
                'sord' => 'ASC',
                'language' => 'en',
            ]);

        $this->assertEquals(1, $response['records']);
        $this->assertEquals('en', $response['rows'][0]['language']);
    }
}
