<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Models\ImportPreset;
use Illuminate\Http\Request;
use Validator;

/**
 * Saved import settings (column mappings ...) for the Bible and volume import dialogs
 */
class ImportPresetController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('install');
        $this->middleware('auth:100');
    }

    /**
     * Presets of one type, by name
     */
    public function index(Request $request): Response
    {
        $resp = new \stdClass();
        $type = $request->input('type');

        if(!is_string($type) || !in_array($type, ImportPreset::getValidTypes(), TRUE)) {
            $resp->success = FALSE;
            $resp->errors  = ['type' => ['A valid type is required']];
            return new Response($resp, 422);
        }

        $resp->success = TRUE;
        $resp->presets = ImportPreset::where('type', $type)->orderBy('name')->get(['id', 'type', 'name', 'settings']);

        return new Response($resp, 200);
    }

    public function store(Request $request): Response
    {
        return $this->_save($request, new ImportPreset());
    }

    /**
     * Use PUT verb.  type cannot be changed.
     */
    public function update(Request $request, string $id): Response
    {
        $Preset = ImportPreset::findOrFail($id);
        $request->merge(['type' => $Preset->type]);

        return $this->_save($request, $Preset);
    }

    /**
     * Use DELETE verb.  Soft deletes.
     */
    public function destroy(string $id): Response
    {
        ImportPreset::findOrFail($id)->delete();

        $resp = new \stdClass();
        $resp->success = TRUE;

        return new Response($resp, 200);
    }

    protected function _save(Request $request, ImportPreset $Preset): Response
    {
        $resp = new \stdClass();
        $type = $request->input('type');
        $data = $request->only(['type', 'name', 'settings']);

        if(isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = ImportPreset::cleanSettings($data['settings']);
        }

        $v = Validator::make($data, ImportPreset::getRules(is_string($type) ? $type : NULL, $Preset->id));

        if($v->fails()) {
            $resp->success = FALSE;
            $resp->errors  = $v->errors();
            return new Response($resp, 422);
        }

        $Preset->fill($data);
        $Preset->save();

        $resp->success = TRUE;
        $resp->preset  = $Preset->only(['id', 'type', 'name', 'settings']);

        return new Response($resp, 200);
    }
}
