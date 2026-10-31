<?php

namespace Tests\Feature\Controllers;

use App\Models\ImportPreset;
use App\User;
use Tests\TestCase;

/**
 * Presets are purpose-built fixtures: each test creates its own (with a unique name) and
 * force deletes them in a finally block.
 */
class ImportPresetControllerTest extends TestCase
{
    protected $User = NULL;

    public function setUp(): void
    {
        parent::setUp();

        $this->User = new User;
        $this->User->name = 'Test Admin';
        $this->User->access_level = 100;
    }

    protected function admin()
    {
        return $this->actingAs($this->User)->withSession(['banned' => FALSE]);
    }

    protected function presetName(): string
    {
        return 'Preset Test ' . bin2hex(random_bytes(4));
    }

    protected function removePresets(string ...$names): void
    {
        ImportPreset::withTrashed()->whereIn('name', $names)->forceDelete();
    }

    public function testGuestIsRefused(): void
    {
        $this->getJson('/admin/import-presets?type=strongs')->assertStatus(401);
        $this->postJson('/admin/import-presets', [])->assertStatus(401);
    }

    public function testCreateListUpdateAndDelete(): void
    {
        $name    = $this->presetName();
        $renamed = $this->presetName();

        try {
            $created = $this->admin()->postJson('/admin/import-presets', [
                'type'     => 'strongs',
                'name'     => $name,
                'settings' => ['first_row_data' => 2, 'col_A' => 'number', 'importer' => 'csv', 'file' => 'x.csv'],
            ]);

            $created->assertStatus(200)->assertJsonPath('preset.settings', ['first_row_data' => 2, 'col_A' => 'number']);
            $id = $created->json('preset.id');

            $this->admin()->getJson('/admin/import-presets?type=strongs')
                ->assertStatus(200)
                ->assertJsonFragment(['id' => $id, 'name' => $name]);

            $this->admin()->getJson('/admin/import-presets?type=bible')
                ->assertStatus(200)
                ->assertJsonMissing(['id' => $id]);

            $this->admin()->putJson('/admin/import-presets/' . $id, [
                'type'     => 'bible',
                'name'     => $renamed,
                'settings' => ['first_row_data' => 3],
            ])->assertStatus(200)->assertJsonPath('preset.type', 'strongs');

            $this->assertSame(['first_row_data' => 3], ImportPreset::find($id)->settings);

            $this->admin()->deleteJson('/admin/import-presets/' . $id)->assertStatus(200);

            $this->assertNull(ImportPreset::find($id));
            $this->assertNotNull(ImportPreset::withTrashed()->find($id), 'Delete is soft');
        }
        finally {
            $this->removePresets($name, $renamed);
        }
    }

    public function testNameIsUniquePerTypeAmongLivePresets(): void
    {
        $name = $this->presetName();

        try {
            $Preset = ImportPreset::factory()->create(['name' => $name]);

            $this->admin()->postJson('/admin/import-presets', ['type' => 'strongs', 'name' => $name, 'settings' => ['a' => 1]])
                ->assertStatus(422)
                ->assertJsonValidationErrors('name');

            $this->admin()->postJson('/admin/import-presets', ['type' => 'bible', 'name' => $name, 'settings' => ['a' => 1]])
                ->assertStatus(200);

            $Preset->delete();

            $this->admin()->postJson('/admin/import-presets', ['type' => 'strongs', 'name' => $name, 'settings' => ['a' => 1]])
                ->assertStatus(200);
        }
        finally {
            $this->removePresets($name);
        }
    }

    public function testValidation(): void
    {
        $this->admin()->getJson('/admin/import-presets?type=bacon')->assertStatus(422);

        $this->admin()->postJson('/admin/import-presets', ['type' => 'bacon', 'name' => '', 'settings' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'name', 'settings']);
    }
}
