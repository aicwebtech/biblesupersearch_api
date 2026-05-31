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

    public function testIndexSyncFeatures()
    {
        // Request index page (which calls syncFeatures)
        $response = $this->actingAs($this->User)
            ->withSession(['banned' => FALSE])
            ->get('/admin/features');

        $response->assertStatus(200);

        // Check that features were synced to database
        $this->assertGreaterThan(0, Feature::count());
    }

    public function testGridIncludesFeatureDefinitionsData()
    {
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

    public function testGridShowsCrossReferencesWithNullLanguage()
    {
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

    public function testGridShowsStrongsWithSeparateRowsPerLanguage()
    {
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
        
        $this->assertGreaterThanOrEqual(3, count($strongsRows));
    }

    public function testSyncDoesNotDuplicateExistingRows()
    {
        Feature::syncFeatures();
        $initialCount = Feature::count();

        // Call sync again
        Feature::syncFeatures();

        $this->assertEquals($initialCount, Feature::count());
    }

    public function testGridSearchByLanguage()
    {
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
