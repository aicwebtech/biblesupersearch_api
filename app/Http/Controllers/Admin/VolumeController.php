<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use App\Http\Responses\Response;
use App\Http\Controllers\Controller;
use App\ContentImportManager;
use App\Models\Volume;
use App\Models\LanguageAttr;
use App\Models\VolumeTypes\Strongs;
use Validator;

class VolumeController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('install');
        $this->middleware('auth:100');
        $this->middleware('migrate')->only('index');
        $this->middleware('dev_tools')->only('export', 'meta');
    }

    /**
     * Display the Volumes manager page
     *
     * @param string|null $type Show only volumes of this type; the route limits it to registered types
     */
    public function index(?string $type = NULL): View
    {
        Volume::populateVolumesTable();
        Volume::updateNeedsUpdate();

        $bootstrap = $this->getAdminBootstrap();
        $bootstrap->volume_types = $this->getVolumeTypesBootstrap();
        $bootstrap->volume_type = $type;
        $bootstrap = $this->encodeBootstrap($bootstrap);

        return view('admin.volumes', ['bootstrap' => $bootstrap]);
    }

    public function grid(Request $request): HttpResponse
    {
        $data = $request->toArray();
        $rows = [];
        $rows_per_page = max((int) ($data['rows'] ?? 20), 1);
        $sidx = $data['sidx'] ?? 'rank';
        $sord = strtoupper((string) ($data['sord'] ?? 'ASC'));

        $sortable_fields = [
            'name',
            'shortname',
            'module',
            'type',
            'year',
            'lang',
            'copy',
            'enabled',
            'installed',
            'official',
            'is_default',
            'rank',
            'updated_at',
        ];

        if(!in_array($sidx, $sortable_fields, TRUE)) {
            $sidx = 'rank';
        }

        if(!in_array($sord, ['ASC', 'DESC'], TRUE)) {
            $sord = 'ASC';
        }

        if($sidx == 'lang') {
            $sidx = 'languages.name';
        }
        else if($sidx == 'copy') {
            $sidx = 'copyrights.name';
        }
        else {
            $sidx = 'volumes.' . $sidx;
        }

        $Query = Volume::select('volumes.*', 'languages.name AS lang', 'copyrights.name AS copy')
            ->leftJoin('languages', 'volumes.language', 'languages.code')
            ->leftJoin('copyrights', 'volumes.copyright_id', 'copyrights.id')
            ->orderBy($sidx, $sord)
            ->orderBy('volumes.id');

        $searchable = [
            'name'          => 'str_inside',
            'shortname'     => 'str_start',
            'module'        => 'str_inside',
            'year'          => 'str_inside',
            'type'          => 'str_exact',
            'lang'          => 'str_exact',
            'copyright_id'  => 'int',
            'enabled'       => 'int',
            'installed'     => 'int',
            'official'      => 'int',
            'is_default'    => 'int',
        ];

        $fields = [
            'lang' => 'volumes.language',
        ];

        foreach($searchable as $key => $type) {
            if(!isset($data[$key]) || !is_scalar($data[$key]) || $data[$key] === '') {
                continue;
            }

            $field = $fields[$key] ?? 'volumes.' . $key;
            $value = (string) $data[$key];

            switch($type) {
                case 'str_inside':
                    $Query->where($field, 'LIKE', '%' . $value . '%');
                    break;
                case 'int':
                    $Query->where($field, (int) $value);
                    break;
                case 'str_exact':
                    $Query->where($field, $value);
                    break;
                case 'str_start':
                default:
                    $Query->where($field, 'LIKE', $value . '%');
            }
        }

        // has_module_file is not a column, so it is filtered here, after the query, and the page cut by hand
        $file_filter = (isset($data['has_module_file']) && in_array((string) $data['has_module_file'], ['0', '1'], TRUE))
            ? (int) $data['has_module_file'] : NULL;

        $Volumes = ($file_filter === NULL) ? $Query->paginate($rows_per_page) : $Query->get();

        foreach($Volumes as $Volume) {
            $row = $Volume->getAttributes();
            unset($row['description']);
            $row['has_module_file'] = $Volume->hasModuleFile() ? 1 : 0;

            // The stored flag, which index() refreshes on page load: needsUpdate() here would
            // stat each file, and maybe open it and write the row, on every grid request
            $row['needs_update'] = ($Volume->installed && $row['has_module_file'] && $Volume->needs_update) ? 1 : 0;

            if($file_filter !== NULL && $row['has_module_file'] !== $file_filter) {
                continue;
            }

            $rows[] = $row;
        }

        if($file_filter !== NULL) {
            $page  = max((int) ($data['page'] ?? 1), 1);
            $count = count($rows);

            return response([
                'total'     => (int) ceil($count / $rows_per_page),
                'page'      => $page,
                'rows'      => array_slice($rows, $rows_per_page * ($page - 1), $rows_per_page),
                'records'   => $count,
                'post'      => TRUE,
            ], 200);
        }

        return response([
            'total'     => $Volumes->lastPage(),
            'page'      => $Volumes->currentPage(),
            'rows'      => $rows,
            'records'   => $Volumes->total(),
            'post'      => FALSE,
        ], 200);
    }

    public function languages(Request $request): HttpResponse
    {
        $type = $request->input('type');

        $Languages = Volume::select('languages.code', 'languages.name')
            ->join('languages', 'volumes.language', 'languages.code')
            ->when(is_string($type) && Volume::getType($type), fn($Query) => $Query->where('volumes.type', $type))
            ->groupBy('languages.code', 'languages.name')
            ->orderBy('languages.name')
            ->get();

        return response(['languages' => $Languages], 200);
    }

    public function create(): HttpResponse
    {
        return response('Not Implemented', 501);
    }

    public function store(Request $request): Response
    {
        return $this->_save($request, NULL);
    }

    public function show(string $id): Response
    {
        $Volume = Volume::findOrFail($id);

        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->Volume  = $Volume->attributesToArray();
        $resp->Volume['has_module_file'] = $Volume->hasModuleFile() ? 1 : 0;

        return new Response($resp, 200);
    }

    public function edit(string $id): HttpResponse
    {
        return response('Not Implemented', 501);
    }

    /**
     * Use PUT verb
     */
    public function update(Request $request, string $id): Response
    {
        return $this->_save($request, $id);
    }

    /**
     * Use DELETE verb (or POST to /admin/volumes/delete/{id})
     */
    public function destroy(string $id): Response
    {
        $Volume = Volume::findOrFail($id);

        $resp = new \stdClass();
        $resp->success = TRUE;

        if($Volume->official) {
            $resp->success = FALSE;
            $resp->errors = ['Cannot delete an official volume'];
            return new Response($resp, 401);
        }

        if($Guard = $this->defaultGuard($Volume, 'delete')) {
            return $Guard;
        }

        // A content table that cannot be dropped keeps the record that owns it
        if($Volume->installed && !$Volume->uninstall()) {
            return $this->actionResponse($Volume);
        }

        // No language may keep pointing at a dictionary that no longer exists
        if($Volume->type == 'strongs') {
            LanguageAttr::where('attribute', Strongs::LANGUAGE_ATTR)->where('value', $Volume->module)->delete();
        }

        $Volume->delete();

        return new Response($resp, 200);
    }

    protected function _save(Request $request, ?string $id = NULL): Response
    {
        $resp = new \stdClass();
        $Volume = $id ? Volume::findOrFail($id) : new Volume();

        if($id) {
            $errors = [];

            foreach(Volume::IMMUTABLE_FIELDS as $field) {
                if($request->has($field) && (string) $request->input($field) !== (string) $Volume->$field) {
                    $errors[$field] = [ucfirst($field) . ' cannot be changed once set'];
                }
            }

            if($errors) {
                $resp->success = FALSE;
                $resp->errors = $errors;
                return new Response($resp, 422);
            }

            // Validate against the stored values, not whatever the client sent (or omitted)
            $request->merge([
                'type'   => $Volume->type,
                'module' => $Volume->module,
            ]);
        }

        $type  = $request->input('type');
        $rules = Volume::getUpdateRules($id, is_string($type) ? $type : NULL);
        $data  = $request->only(array_keys($rules));

        $v = Validator::make($data, $rules, [], $Volume->attributes());

        if($v->fails()) {
            $resp->success = FALSE;
            $resp->errors = $v->errors();
            return new Response($resp, 422);
        }

        $Volume->fill($data);
        $Volume->save();

        $resp->success = TRUE;
        $resp->Volume  = $Volume->attributesToArray();

        return new Response($resp, 200);
    }

    public function enable(Request $request, string $id): Response
    {
        $Volume = Volume::findOrFail($id);
        $Volume->enable();

        $resp = new \stdClass();
        $resp->success = (bool) $Volume->enabled;

        if(!$Volume->enabled) {
            $resp->errors = ['Cannot enable, volume is not installed.'];
        }

        return new Response($resp, 200);
    }

    public function disable(Request $request, string $id): Response
    {
        $Volume = Volume::findOrFail($id);

        if($Guard = $this->defaultGuard($Volume, 'disable')) {
            return $Guard;
        }

        $Volume->disable();

        $resp = new \stdClass();
        $resp->success = TRUE;

        return new Response($resp, 200);
    }

    public function install(Request $request, string $id): Response
    {
        $Volume = Volume::findOrFail($id);
        $Volume->install(false, (bool) $request->input('enable', FALSE));

        return $this->actionResponse($Volume);
    }

    public function uninstall(Request $request, string $id): Response
    {
        $Volume = Volume::findOrFail($id);

        if($Guard = $this->defaultGuard($Volume, 'uninstall')) {
            return $Guard;
        }

        $Volume->uninstall();

        return $this->actionResponse($Volume);
    }

    /**
     * Export Module: writes the module file (dev tools only)
     */
    public function export(Request $request, string $id): Response
    {
        $Volume = Volume::findOrFail($id);
        $Volume->export((bool) $request->input('overwrite', FALSE));

        return $this->actionResponse($Volume);
    }

    /**
     * Export Meta: rewrites the module file's info.json (dev tools only)
     */
    public function meta(Request $request, string $id): Response
    {
        $Volume = Volume::findOrFail($id);
        $Volume->updateMetaInfo((bool) $request->input('create_new', FALSE));

        return $this->actionResponse($Volume);
    }

    /**
     * Revert: reloads the volume's settings from its module file
     */
    public function revert(Request $request, string $id): Response
    {
        $Volume = Volume::findOrFail($id);
        $Volume->revertMetaInfo();

        return $this->actionResponse($Volume);
    }

    /**
     * Update: reinstalls the volume from a newer module file.  No default guard: the volume is
     * installed again straight away and stays the default.
     */
    public function updateModule(Request $request, string $id): Response
    {
        $Volume = Volume::findOrFail($id);
        $Volume->updateModule();

        return $this->actionResponse($Volume);
    }

    /**
     * Makes the volume the default of its type
     */
    public function makeDefault(Request $request, string $id): Response
    {
        $Volume = Volume::findOrFail($id);
        $Volume->makeDefault();

        return $this->actionResponse($Volume);
    }

    /**
     * Checks an uploaded content import file; see ContentImportManager::checkImportFile()
     */
    public function importCheck(Request $request): Response
    {
        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->errors  = [];

        $Manager = new ContentImportManager();

        if($Manager->checkImportFile($request->all())) {
            $resp->volume  = $Manager->parsed_attributes ?: [];
            $resp->file    = $Manager->sanitized_filename;
            $resp->notices = $Manager->notices;
        }
        else {
            $resp->success = FALSE;
            $resp->errors  = $Manager->getErrors();
        }

        return new Response($resp, $Manager->getHttpStatus());
    }

    /**
     * Imports a checked file into a new volume, or replaces the content of an unofficial one (id)
     */
    public function import(Request $request): Response
    {
        $resp = new \stdClass();
        $resp->success = TRUE;

        $Manager   = new ContentImportManager();
        $volume_id = $request->input('id') ? (int) $request->input('id') : NULL;
        $type      = $request->input('_content_type');
        $Volume    = NULL;

        if($volume_id) {
            $Volume = Volume::findOrFail($volume_id);

            if($Volume->official) {
                $resp->success = FALSE;
                $resp->errors  = ['Cannot overwrite official volumes'];
                return new Response($resp, 422);
            }

            if($Guard = $this->defaultGuard($Volume, 'replace')) {
                return $Guard;
            }

            // Validate against the stored values, not whatever the client sent (or omitted)
            $request->merge(['module' => $Volume->module]);
            $type = $Volume->type;
        }

        $request->merge(['type' => is_string($type) ? $type : NULL, '_content_type' => $type]);

        $rules = ContentImportManager::getImportRules($volume_id, is_string($type) ? $type : NULL);
        $data  = $request->only(array_keys($rules));

        $v = Validator::make($data, $rules, [], (new Volume())->attributes());

        if($v->fails()) {
            $resp->success = FALSE;
            $resp->errors  = $v->errors();
            return new Response($resp, 422);
        }

        if($Manager->importFile($data, $Volume)) {
            $resp->volume  = $Manager->parsed_attributes ?: [];
            $resp->rows    = $Manager->rows_imported;
            $resp->notices = $Manager->notices;
        }
        else {
            $resp->success = FALSE;
            $resp->errors  = $Manager->getErrors();
        }

        return new Response($resp, $Manager->getHttpStatus());
    }

    /**
     * Volume types for the UI, each with its import sources and mappable import fields
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getVolumeTypesBootstrap(): array
    {
        $types = [];

        foreach(Volume::getTypes() as $type => $settings) {
            $content_class = Volume::getContentClassName($type);

            $types[$type] = [
                'label'         => $settings['label'],
                'label_plural'  => $settings['label_plural'],
                'label_short'   => $settings['label_short'],
                'importers'     => ContentImportManager::getImportersList($type),
                'import_fields' => $content_class ? $content_class::getImportFieldList() : [],
            ];
        }

        return $types;
    }

    /**
     * The default volume of a type must stay usable, as the default Bible must: another volume
     * has to be made the default first.
     *
     * @param Volume $Volume
     * @param string $action Verb for the error message
     * @return Response|null 422 response if the action is refused
     */
    protected function defaultGuard(Volume $Volume, string $action): ?Response
    {
        if(!$Volume->isDefault()) {
            return NULL;
        }

        $resp = new \stdClass();
        $resp->success = FALSE;
        $resp->errors  = ['Cannot ' . $action . ' the default volume; make another volume the default first.'];

        return new Response($resp, 422);
    }

    protected function actionResponse(Volume $Volume): Response
    {
        $resp = new \stdClass();
        $resp->success = TRUE;

        if($Volume->hasErrors()) {
            $resp->success = FALSE;
            $resp->errors  = $Volume->getErrors();
        }

        return new Response($resp, 200);
    }
}
