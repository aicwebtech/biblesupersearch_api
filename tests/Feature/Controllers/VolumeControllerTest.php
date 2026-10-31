<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\User;
use App\Models\Volume;
use App\Models\Copyright;
use App\Models\Language;
use App\Models\VolumeTypes\Strongs;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class VolumeControllerTest extends TestCase
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

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function volumeInput(array $overrides = []): array
    {
        $suffix = bin2hex(random_bytes(3));

        // Whatever gets created from this input - including by a request that should have been
        // refused - is removed in tearDown
        $this->deferCleanup(fn() => $this->removeVolumeModuleFixture('strongs', 'vol_ctrl_' . $suffix));

        return array_merge([
            'type'          => 'strongs',
            'name'          => 'Volume Controller Fixture ' . $suffix,
            'shortname'     => 'VolCtrl ' . $suffix,
            'module'        => 'vol_ctrl_' . $suffix,
            'language'      => 'en',
            'copyright_id'  => Copyright::first()->id,
            'year'          => '1890',
        ], $overrides);
    }

    protected function makeVolumeFixture(array $overrides = []): Volume
    {
        $Volume = new Volume();
        $Volume->fill($this->volumeInput($overrides));

        if(array_key_exists('official', $overrides)) {
            $Volume->official = $overrides['official'];
        }

        $Volume->save();

        return $Volume;
    }

    /**
     * Drops the content table as well as the row: installing now creates one.
     */
    protected function removeVolumeFixture(?Volume $Volume): void
    {
        if(!$Volume) {
            return;
        }

        if(Volume::getContentClassName($Volume->type)) {
            $Volume->content()->uninstall();
        }

        $Volume->forceDelete();
    }

    protected function removeByModule(string $module): void
    {
        foreach(Volume::where('module', $module)->get() as $Volume) {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testGuestIsRedirected(): void
    {
        $this->get('/admin/volumes')->assertStatus(302);
        $this->getJson('/admin/volumes/grid')->assertStatus(401);
        $this->postJson('/admin/volumes', $this->volumeInput())->assertStatus(401);
    }

    public function testNonAdminIsForbidden(): void
    {
        $User = new User;
        $User->name = 'Test User';
        $User->access_level = 1;

        $this->actingAs($User)->withSession(['banned' => FALSE])
            ->getJson('/admin/volumes/grid')
            ->assertStatus(403);
    }

    public function testIndexProvidesVolumeTypes(): void
    {
        $response = $this->admin()->get('/admin/volumes');

        $response->assertStatus(200);
        $response->assertSee('volumes/Bootstrap.vue.js', FALSE);
        $response->assertSee('volume_types', FALSE);
    }

    public function testIndexProvidesTypeLabels(): void
    {
        $response = $this->admin()->get('/admin/volumes');

        $response->assertStatus(200);
        $response->assertSee('"label_plural"', FALSE);
        $response->assertSee('"label_short"', FALSE);
        $response->assertSee('"volume_type":null', FALSE);
    }

    public function testTypeRouteBootstrapsTheType(): void
    {
        $this->get('/admin/volumes/strongs')->assertStatus(302);

        $response = $this->admin()->get('/admin/volumes/strongs');

        $response->assertStatus(200);
        $response->assertSee('volumes/Bootstrap.vue.js', FALSE);
        $response->assertSee('"volume_type":"strongs"', FALSE);
    }

    public function testUnknownTypeIsNotAVolumesPage(): void
    {
        // Falls through to show(), which finds no such volume
        $this->admin()->getJson('/admin/volumes/not_a_type')->assertStatus(404);
    }

    public function testStoreShowAndUpdate(): void
    {
        $input = $this->volumeInput();

        try {
            $response = $this->admin()->postJson('/admin/volumes', $input);
            $response->assertStatus(200)->assertJson(['success' => TRUE]);

            $id = $response['Volume']['id'];

            $this->admin()->getJson('/admin/volumes/' . $id)
                ->assertStatus(200)
                ->assertJsonPath('Volume.module', $input['module'])
                ->assertJsonPath('Volume.type', 'strongs');

            $update = array_merge($input, ['name' => $input['name'] . ' Updated', 'rank' => 5]);

            $this->admin()->putJson('/admin/volumes/' . $id, $update)
                ->assertStatus(200)
                ->assertJsonPath('Volume.name', $update['name']);

            $this->assertSame(5, (int) Volume::find($id)->rank);
        }
        finally {
            $this->removeByModule($input['module']);
        }
    }

    public function testUpdateWithoutTypeOrModuleKeepsThem(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $input = $this->volumeInput(['name' => $Volume->name, 'shortname' => $Volume->shortname]);
            unset($input['type'], $input['module']);

            $this->admin()->putJson('/admin/volumes/' . $Volume->id, $input)->assertStatus(200);

            $Volume->refresh();
            $this->assertSame('strongs', $Volume->type);
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testUpdateRejectsChangingTypeOrModule(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $base = $this->volumeInput(['module' => $Volume->module, 'name' => $Volume->name, 'shortname' => $Volume->shortname]);

            $this->admin()->putJson('/admin/volumes/' . $Volume->id, array_merge($base, ['module' => 'changed_module']))
                ->assertStatus(422)
                ->assertJsonPath('success', FALSE)
                ->assertJsonStructure(['errors' => ['module']]);

            $this->admin()->putJson('/admin/volumes/' . $Volume->id, array_merge($base, ['type' => 'commentary']))
                ->assertStatus(422)
                ->assertJsonStructure(['errors' => ['type']]);

            $Volume->refresh();
            $this->assertSame('strongs', $Volume->type);
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testStoreValidation(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();

            $cases = [
                'type'      => ['type' => 'not_a_type'],
                'module'    => ['module' => 'Bad-Module'],
                'language'  => ['language' => ''],
                'name'      => ['name' => $Volume->name],
                'shortname' => ['shortname' => $Volume->shortname],
            ];

            foreach($cases as $field => $override) {
                $this->admin()->postJson('/admin/volumes', $this->volumeInput($override))
                    ->assertStatus(422)
                    ->assertJsonStructure(['errors' => [$field]]);
            }

            $this->admin()->postJson('/admin/volumes', $this->volumeInput(['module' => $Volume->module]))
                ->assertStatus(422)
                ->assertJsonStructure(['errors' => ['module']]);

            $this->assertSame(1, Volume::where('module', $Volume->module)->count());
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testActions(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $url = '/admin/volumes/';

            $this->admin()->postJson($url . 'enable/' . $Volume->id)
                ->assertStatus(200)
                ->assertJson(['success' => FALSE]);

            $this->admin()->postJson($url . 'install/' . $Volume->id . '?enable=1')
                ->assertStatus(200)
                ->assertJson(['success' => TRUE]);

            $Volume->refresh();
            $this->assertSame(1, (int) $Volume->installed);
            $this->assertSame(1, (int) $Volume->enabled);

            $this->admin()->postJson($url . 'install/' . $Volume->id)->assertJson(['success' => FALSE]);

            $this->admin()->postJson($url . 'disable/' . $Volume->id)->assertJson(['success' => TRUE]);
            $this->assertSame(0, (int) $Volume->refresh()->enabled);

            $this->admin()->postJson($url . 'enable/' . $Volume->id)->assertJson(['success' => TRUE]);
            $this->assertSame(1, (int) $Volume->refresh()->enabled);

            $table = $Volume->content()->getTable();
            $this->assertTrue(Schema::hasTable($table));

            $this->admin()->postJson($url . 'uninstall/' . $Volume->id)->assertJson(['success' => TRUE]);
            $Volume->refresh();
            $this->assertSame(0, (int) $Volume->installed);
            $this->assertSame(0, (int) $Volume->enabled);
            $this->assertFalse(Schema::hasTable($table));
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testGridFiltersAndSorts(): void
    {
        $Volume = $Other = NULL;

        try {
            $Volume = $this->makeVolumeFixture(['language' => 'en']);
            $Other  = $this->makeVolumeFixture(['language' => 'ru']);

            $query = [
                'rows' => 25,
                'page' => 1,
                'sidx' => 'name; DROP TABLE volumes',
                'sord' => 'sideways',
                'module' => 'vol_ctrl_',
            ];

            $response = $this->admin()->getJson('/admin/volumes/grid?' . http_build_query($query));
            $response->assertStatus(200);
            $modules = array_column($response['rows'], 'module');
            $this->assertContains($Volume->module, $modules);
            $this->assertContains($Other->module, $modules);
            $this->assertArrayNotHasKey('description', $response['rows'][0]);

            $response = $this->admin()->getJson('/admin/volumes/grid?' . http_build_query($query + ['lang' => 'ru']));
            $modules = array_column($response['rows'], 'module');
            $this->assertContains($Other->module, $modules);
            $this->assertNotContains($Volume->module, $modules);

            $response = $this->admin()->getJson('/admin/volumes/grid?' . http_build_query($query + ['type' => 'commentary']));
            $this->assertSame(0, $response['records']);

            $response = $this->admin()->getJson('/admin/volumes/grid?' . http_build_query(['module' => $Volume->module, 'installed' => 0]));
            $response->assertStatus(200);
            $this->assertSame(1, $response['records']);
            $this->assertSame('English', $response['rows'][0]['lang']);
        }
        finally {
            $this->removeVolumeFixture($Other);
            $this->removeVolumeFixture($Volume);
        }
    }

    /**
     * The grid reports the stored needs_update flag, which index() refreshes, and does not read
     * module files or write rows itself
     */
    public function testGridReportsTheStoredNeedsUpdateFlag(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $this->assertTrue($Volume->install(FALSE, TRUE));
            $this->deferCleanup(fn() => is_file($Volume->getModuleFilePath()) && unlink($Volume->getModuleFilePath()));
            $this->assertTrue($Volume->export());

            Volume::whereKey($Volume->id)->update(['needs_update' => 1, 'module_checked_at' => NULL, 'installed_at' => '2000-01-01 00:00:00']);

            $response = $this->admin()->getJson('/admin/volumes/grid?' . http_build_query(['module' => $Volume->module]));
            $response->assertStatus(200);
            $this->assertSame(1, $response['rows'][0]['has_module_file']);
            $this->assertSame(1, $response['rows'][0]['needs_update']);
            $this->assertNull($Volume->fresh()->module_checked_at, 'The grid read the module file');

            // Without its module file, a volume has nothing to update from
            unlink($Volume->getModuleFilePath());

            $response = $this->admin()->getJson('/admin/volumes/grid?' . http_build_query(['module' => $Volume->module]));
            $this->assertSame(0, $response['rows'][0]['needs_update']);
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testLanguagesFiltersByType(): void
    {
        $codes = fn(array $query) => collect($this->admin()->getJson('/admin/volumes/languages?' . http_build_query($query))['languages'])
            ->pluck('code')
            ->reject(fn($code) => preg_match(\App\Console\Commands\TestCleanup::LANGUAGE_PATTERN, (string) $code))
            ->sort()->values()->all();

        $strongs = Volume::where('type', 'strongs')->distinct()->pluck('language')
            ->reject(fn($code) => preg_match(\App\Console\Commands\TestCleanup::LANGUAGE_PATTERN, (string) $code))
            ->sort()->values()->all();

        $this->assertSame($strongs, $codes(['type' => 'strongs']));

        // An unknown type is ignored rather than matching nothing
        $this->assertSame($codes([]), $codes(['type' => 'not_a_type']));
        $this->assertSame($codes([]), $codes(['type' => ['strongs']]));
    }

    public function testLanguagesListsOnlyLanguagesWithVolumes(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture(['language' => 'ru']);

            $codes = array_column($this->admin()->getJson('/admin/volumes/languages')['languages'], 'code');

            $this->assertContains('ru', $codes);

            // Throwaway languages (qq?) are other workers' fixtures, which come and go between the two reads
            $real = fn(iterable $codes) => collect($codes)
                ->reject(fn($code) => preg_match(\App\Console\Commands\TestCleanup::LANGUAGE_PATTERN, (string) $code))
                ->sort()->values()->all();

            $this->assertSame($real(Volume::distinct()->pluck('language')), $real($codes));
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testDestroy(): void
    {
        $Volume = $Official = NULL;

        try {
            $Volume   = $this->makeVolumeFixture();
            $Official = $this->makeVolumeFixture(['official' => 1]);

            $this->admin()->postJson('/admin/volumes/delete/' . $Official->id)
                ->assertStatus(401)
                ->assertJson(['success' => FALSE]);

            $this->assertNotNull(Volume::find($Official->id));

            $Volume->install();
            $table = $Volume->content()->getTable();
            $this->assertTrue(Schema::hasTable($table));

            $this->admin()->deleteJson('/admin/volumes/' . $Volume->id)
                ->assertStatus(200)
                ->assertJson(['success' => TRUE]);

            $this->assertNull(Volume::find($Volume->id));
            $this->assertFalse(Schema::hasTable($table), 'Deleting an installed volume must drop its content table');
            $Volume = NULL;
        }
        finally {
            $this->removeVolumeFixture($Official);
            $this->removeVolumeFixture($Volume);
        }
    }

    /**
     * An installed, enabled volume of an unregistered type: see VolumeTest::makeUsableDefaultFixture()
     * for why the default tests stay off the Strong's type.
     */
    protected function makeDefaultFixture(): Volume
    {
        $Volume = $this->makeVolumeFixture(['type' => 'default_fixture']);
        $Volume->installed = 1;
        $Volume->enabled = 1;
        $Volume->save();

        return $Volume;
    }

    public function testMakeDefault(): void
    {
        $Volume = $Uninstalled = NULL;

        try {
            $Volume      = $this->makeDefaultFixture();
            $Uninstalled = $this->makeVolumeFixture(['type' => 'default_fixture']);

            $this->admin()->postJson('/admin/volumes/default/' . $Uninstalled->id)
                ->assertStatus(200)
                ->assertJson(['success' => FALSE]);

            $this->admin()->postJson('/admin/volumes/default/' . $Volume->id)
                ->assertStatus(200)
                ->assertJson(['success' => TRUE]);

            $this->assertSame(1, (int) $Volume->refresh()->is_default);

            $rows = $this->admin()->getJson('/admin/volumes/grid?' . http_build_query(['type' => 'default_fixture', 'is_default' => 1]))['rows'];
            $this->assertSame([$Volume->id], array_column($rows, 'id'));
        }
        finally {
            $this->removeVolumeFixture($Uninstalled);
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testTheDefaultCannotBeDisabledUninstalledOrDeleted(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeDefaultFixture();
            $Volume->makeDefault();

            foreach(['disable', 'uninstall', 'delete'] as $action) {
                $this->admin()->postJson('/admin/volumes/' . $action . '/' . $Volume->id)
                    ->assertStatus(422)
                    ->assertJson(['success' => FALSE]);
            }

            $this->admin()->deleteJson('/admin/volumes/' . $Volume->id)->assertStatus(422);

            $Volume->refresh();
            $this->assertSame(1, (int) $Volume->installed);
            $this->assertSame(1, (int) $Volume->enabled);
            $this->assertSame(1, (int) $Volume->is_default);
        }
        finally {
            if($Volume) {
                $Volume->forceDelete(); // unregistered type: no content table to drop
            }
        }
    }

    public function testDeletingAStrongsDictionaryClearsLanguageDefaults(): void
    {
        $Volume = NULL;

        try {
            $Volume   = $this->makeVolumeFixture();
            $Language = $this->createLanguageFixture('qqc', 'Volume Controller Fixture Language');
            $Language->setAttr(Strongs::LANGUAGE_ATTR, $Volume->module);

            $this->admin()->postJson('/admin/volumes/delete/' . $Volume->id)->assertStatus(200);
            $Volume = NULL;

            $this->assertNull(Language::getLanguageAttr('qqc', Strongs::LANGUAGE_ATTR));
        }
        finally {
            $this->removeVolumeFixture($Volume);
            $this->removeLanguageFixture('qqc');
        }
    }

    /**
     * An installed Strong's volume holding one row, whose module file is removed afterwards
     * by removeModuleFixture()
     */
    protected function makeModuleFixture(): Volume
    {
        $Volume = $this->makeVolumeFixture();
        $Volume->install(FALSE, TRUE);
        DB::table($Volume->content()->getTable())->insert(['number' => 'H1', 'definition' => 'father', 'is_special' => 0]);

        return $Volume;
    }

    protected function removeModuleFixture(?Volume $Volume): void
    {
        if($Volume) {
            $file = $Volume->getModuleFilePath();
            is_file($file) && unlink($file);
        }

        $this->removeVolumeFixture($Volume);
    }

    public function testExportAndMetaNeedDevTools(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeModuleFixture();
            config(['bss.dev_tools' => FALSE]);

            foreach(['export', 'meta'] as $action) {
                $this->admin()->postJson('/admin/volumes/' . $action . '/' . $Volume->id)->assertStatus(503);
            }

            $this->assertFalse($Volume->hasModuleFile());
        }
        finally {
            $this->removeModuleFixture($Volume);
        }
    }

    public function testModuleFileActions(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeModuleFixture();
            config(['bss.dev_tools' => TRUE]);
            $url = '/admin/volumes/';

            $this->admin()->postJson($url . 'export/' . $Volume->id)->assertStatus(200)->assertJson(['success' => TRUE]);
            $this->assertTrue($Volume->hasModuleFile());

            // Exists: refused without overwrite
            $this->admin()->postJson($url . 'export/' . $Volume->id)->assertJson(['success' => FALSE]);
            $this->admin()->postJson($url . 'export/' . $Volume->id . '?overwrite=1')->assertJson(['success' => TRUE]);

            $this->admin()->getJson($url . $Volume->id)->assertJsonPath('Volume.has_module_file', 1);

            $grid = fn(int $has) => array_column($this->admin()->getJson($url . 'grid?' . http_build_query([
                'module' => $Volume->module, 'has_module_file' => $has,
            ]))['rows'], 'id');

            $this->assertSame([$Volume->id], $grid(1));
            $this->assertSame([], $grid(0));

            // Revert undoes a local edit
            $name = $Volume->name;
            $Volume->name = $name . ' Edited';
            $Volume->save();

            $this->admin()->postJson($url . 'revert/' . $Volume->id)->assertJson(['success' => TRUE]);
            $this->assertSame($name, $Volume->refresh()->name);

            $this->admin()->postJson($url . 'meta/' . $Volume->id)->assertJson(['success' => TRUE]);

            // Nothing newer in the file
            $this->admin()->postJson($url . 'update/' . $Volume->id)->assertJson(['success' => FALSE]);
        }
        finally {
            $this->removeModuleFixture($Volume);
        }
    }

    public function testMissingVolumeIs404(): void
    {
        $this->admin()->getJson('/admin/volumes/999999999')->assertStatus(404);
    }

    // -----------------------------------------------------------------------
    // Content import
    // -----------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    protected function importMapping(): array
    {
        return ['first_row_data' => 2, 'col_A' => 'number', 'col_B' => 'definition'];
    }

    protected function makeImportCsv(): \Illuminate\Http\UploadedFile
    {
        $name = 'vol_ctrl_' . bin2hex(random_bytes(4)) . '.csv';
        $path = sys_get_temp_dir() . '/' . $name;
        file_put_contents($path, "Number,Definition\nH1,One\nG2,Two\n");

        return new \Illuminate\Http\UploadedFile($path, $name, 'text/csv', NULL, TRUE);
    }

    protected function removeImportUpload(?string $file): void
    {
        $path = Volume::getModulePath('strongs') . 'uploads/' . $file;

        if($file && is_file($path)) {
            unlink($path);
        }
    }

    public function testImportEndpointsRequireAnAdmin(): void
    {
        $this->postJson('/admin/volumes/importcheck', [])->assertStatus(401);
        $this->postJson('/admin/volumes/import', [])->assertStatus(401);
    }

    public function testIndexBootstrapsImportersAndFields(): void
    {
        $response = $this->admin()->get('/admin/volumes');

        $response->assertStatus(200);
        $response->assertSee('import_fields', FALSE);
        $response->assertSee('number_hg', FALSE);
    }

    public function testCheckThenImportOverHttp(): void
    {
        $input = $this->volumeInput();
        $File  = $this->makeImportCsv();
        $file  = NULL;

        try {
            $check = $this->admin()->post('/admin/volumes/importcheck', array_merge($this->importMapping(), [
                'importer'     => 'csv',
                'content_type' => 'strongs',
                'file'         => $File,
            ]));

            $file = $check->json('file');
            $check->assertStatus(200)->assertJson(['success' => TRUE, 'volume' => ['type' => 'strongs']]);

            $data = $input;
            unset($data['type']);

            $import = $this->admin()->postJson('/admin/volumes/import', array_merge($data, [
                '_importer'     => 'csv',
                '_content_type' => 'strongs',
                '_file'         => $file,
                '_settings'     => json_encode($this->importMapping()),
            ]));

            $import->assertStatus(200)->assertJson(['success' => TRUE, 'rows' => 2]);

            $Volume = Volume::findByTypeAndModule('strongs', $input['module']);
            $this->assertSame(['H1', 'G2'], $Volume->content()->newQuery()->orderBy('id')->pluck('number')->all());
        }
        finally {
            $this->removeByModule($input['module']);
            $this->removeImportUpload($file);
            @unlink($File->getPathname());
        }
    }

    public function testImportValidatesTheVolumeRecord(): void
    {
        $response = $this->admin()->postJson('/admin/volumes/import', [
            '_importer'     => 'csv',
            '_content_type' => 'strongs',
            '_file'         => 'whatever.csv',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', FALSE);
        $this->assertArrayHasKey('name', $response->json('errors'));
        $this->assertArrayHasKey('module', $response->json('errors'));
    }

    public function testImportRefusesToReplaceAnOfficialVolume(): void
    {
        $Volume = $this->makeVolumeFixture(['official' => 1]);

        try {
            $this->admin()->postJson('/admin/volumes/import', [
                'id'            => $Volume->id,
                '_importer'     => 'csv',
                '_content_type' => 'strongs',
                '_file'         => 'whatever.csv',
            ])->assertStatus(422)->assertJsonPath('errors.0', 'Cannot overwrite official volumes');
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }
}
