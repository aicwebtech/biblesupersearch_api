<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Responses\Response;
use App\Http\Controllers\Controller;
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
    }

    /**
     * Display the Volumes manager page
     */
    public function index()
    {
        $bootstrap = $this->getAdminBootstrap();
        $bootstrap->volume_types = Volume::getTypes();
        $bootstrap = $this->encodeBootstrap($bootstrap);

        return view('admin.volumes', ['bootstrap' => $bootstrap]);
    }

    public function grid(Request $request)
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

        $Volumes = $Query->paginate($rows_per_page);

        foreach($Volumes as $Volume) {
            $row = $Volume->getAttributes();
            unset($row['description']);
            $rows[] = $row;
        }

        return response([
            'total'     => $Volumes->lastPage(),
            'page'      => $Volumes->currentPage(),
            'rows'      => $rows,
            'records'   => $Volumes->total(),
            'post'      => FALSE,
        ], 200);
    }

    public function languages(Request $request)
    {
        $Languages = Volume::select('languages.code', 'languages.name')
            ->join('languages', 'volumes.language', 'languages.code')
            ->groupBy('languages.code', 'languages.name')
            ->orderBy('languages.name')
            ->get();

        return response(['languages' => $Languages], 200);
    }

    public function create()
    {
        return response('Not Implemented', 501);
    }

    public function store(Request $request)
    {
        return $this->_save($request, NULL);
    }

    public function show($id)
    {
        $Volume = Volume::findOrFail($id);

        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->Volume  = $Volume->attributesToArray();

        return new Response($resp, 200);
    }

    public function edit($id)
    {
        return response('Not Implemented', 501);
    }

    /**
     * Use PUT verb
     */
    public function update(Request $request, $id)
    {
        return $this->_save($request, $id);
    }

    /**
     * Use DELETE verb (or POST to /admin/volumes/delete/{id})
     */
    public function destroy($id)
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

        if($Volume->installed) {
            $Volume->uninstall();
        }

        // No language may keep pointing at a dictionary that no longer exists
        if($Volume->type == 'strongs') {
            LanguageAttr::where('attribute', Strongs::LANGUAGE_ATTR)->where('value', $Volume->module)->delete();
        }

        $Volume->delete();

        return new Response($resp, 200);
    }

    protected function _save(Request $request, $id = NULL)
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

    public function enable(Request $request, $id)
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

    public function disable(Request $request, $id)
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

    public function install(Request $request, $id)
    {
        $Volume = Volume::findOrFail($id);
        $Volume->install(false, (bool) $request->input('enable', FALSE));

        return $this->actionResponse($Volume);
    }

    public function uninstall(Request $request, $id)
    {
        $Volume = Volume::findOrFail($id);

        if($Guard = $this->defaultGuard($Volume, 'uninstall')) {
            return $Guard;
        }

        $Volume->uninstall();

        return $this->actionResponse($Volume);
    }

    /**
     * Makes the volume the default of its type
     */
    public function makeDefault(Request $request, $id)
    {
        $Volume = Volume::findOrFail($id);
        $Volume->makeDefault();

        return $this->actionResponse($Volume);
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
