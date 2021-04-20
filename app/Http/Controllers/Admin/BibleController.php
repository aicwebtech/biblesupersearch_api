<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
//use Illuminate\Http\Response;
use App\Http\Responses\Response;
use App\Http\Controllers\Controller;
use App\Models\Bible;
use App\Helpers;
use Validator;

class BibleController extends Controller
{
    public function __construct() {
        parent::__construct();
        $this->middleware('install');
        $this->middleware('auth:100');
        $this->middleware('migrate')->only('index');
        $this->middleware('dev_tools')->only('export', 'meta');
    }

    /**
     * Display a listing of the resource.
     * In this case, a page with a jqGrid
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        Bible::populateBibleTable();
        $ImportManagerClass = Helpers::find('\App\ImportManager');

        $bootstrap = new \stdClass;
        $bootstrap->devToolsEnabled  = config('bss.dev_tools') ? TRUE : FALSE;
        $bootstrap->premToolsEnabled = config('app.premium');
        $bootstrap->maxUploadSize    = Helpers::maxUploadSize('both');
        $bootstrap->languages  = \App\Models\Language::orderBy('name', 'asc')->get();
        $bootstrap->copyrights = [];
        $bootstrap->importers  = $ImportManagerClass::getImportersList();

        foreacH(\App\Models\Copyright::all() as $Copyright) {
            $data = $Copyright->getAttributes();
            $data['copyright_statement_processed'] = $Copyright->getProcessedCopyrightStatement();
            $bootstrap->copyrights[] = $data;
        }

        $bootstrap = json_encode($bootstrap);

        return view('admin.bibles', ['bootstrap' => $bootstrap]);
    }

    public function grid(Request $request) {
        $data = $request->toArray();
        $rows = [];
        $rows_per_page = intval($data['rows']);

        if($data['sidx'] == 'lang') {
            $data['sidx'] = 'languages.name';
        }
        else {
            $data['sidx'] = 'bibles.' . $data['sidx'];
        }

        $Bibles = Bible::select('bibles.*', 'languages.name AS lang')
            ->leftJoin('languages', 'bibles.lang_short', 'languages.code')
            ->orderBy($data['sidx'], $data['sord'])
            ->paginate($rows_per_page);

        foreach($Bibles as $Bible) {
            $row = $Bible->getAttributes();
            unset($row['description']);
            $row['has_module_file'] = $Bible->hasModuleFile() ? 1 : 0;
            $row['needs_update']    = $Bible->needsUpdate() ? 1 : 0;
            $rows[] = $row;
        }

        $resp = [
            'total'     => $Bibles->lastPage(),
            'page'      => $Bibles->currentPage(),
            'rows'      => $rows,
            'records'   => $Bibles->total(),
        ];

        return response($resp, 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        return response('Not Implemented', 501);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $this->_save($request, NULL);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        $Bible = Bible::findOrFail($id);

        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->Bible   = $Bible->attributesToArray();
        $resp->Bible['has_module_file'] = $Bible->hasModuleFile() ? 1 : 0;
        // $resp->Bible['has_module_file'] =  0; // Debugging

        return new Response($resp, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        return response('Not Implemented', 501);
    }

    /**
     * Update the specified resource in storage.
     * Use PUT verb
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        return $this->_save($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     * Use DELETE verb
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $Bible = Bible::findOrFail($id);

        $resp = new \stdClass();
        $resp->success = TRUE;
        
        if($Bible->hasModuleFile() || $Bible->official) {
            $resp->success = FALSE;
            $resp->errors = ['Cannot delete an official Bible or a Bible that has a module file'];
            return new Response($resp, 401);
        }

        $Bible->uninstall();
        $Bible->delete();

        // if($Bible->hasErrors()) {
        //     $resp->success = FALSE;
        //     $resp->errors  = $Bible->getErrors();
        // }

        return new Response($resp, 200);
    }

    protected function _save(Request $request, $id = NULL) {
        $resp = new \stdClass();

        $BibleClass = Helpers::find('\App\Models\Bible');

        if($id) {
            $Bible = Bible::findOrFail($id);
        }
        else {
            $Bible = new Bible();
        }

        $rules = $BibleClass::getUpdateRules($id);

        $data = $request->only(array_keys($rules));

        // $request->validate($rules); // This breaks, even though docs say it will return 422 with JSON data for an AJAX request

        $v = Validator::make($data, $rules);

        if($v->fails()) {
            $resp->success = FALSE;
            $resp->errors = $v->errors();
            return new Response($resp, 422);
        }

        $Bible->fill($data);
        $Bible->save();

        $resp->success = TRUE;
        $resp->Bible   = $Bible->attributesToArray();

        return new Response($resp, 200);
    }

    public function enable(Request $request, $id) {
        $Bible = Bible::findOrFail($id);
        $Bible->enabled = 1;
        $Bible->save();

        $resp = [
            'success' => ($Bible->enabled) ? TRUE : FALSE,
        ];

        if(!$Bible->enabled) {
            $resp['errors'] = ['Cannot enable, Bible is not installed.'];
        }

        return new Response($resp, 200);
    }

    public function disable(Request $request, $id) {
        $Bible = Bible::findOrFail($id);
        $Bible->enabled = 0;
        $Bible->save();

        $resp = [
            'success' => TRUE,
        ];

        return new Response($resp, 200);
    }

    public function install(Request $request, $id) {
        $Bible = Bible::findOrFail($id);
        $data  = $request->toArray();
        $enable = (array_key_exists('enable', $data) && $data['enable']) ? TRUE : FALSE;
        $Bible->install(FALSE, $enable);

        $resp = new \stdClass();
        $resp->success = TRUE;

        if($Bible->hasErrors()) {
            $resp->success = FALSE;
            $resp->errors  = $Bible->getErrors();
        }

        return new Response($resp, 200);
    }    

    public function updateModule(Request $request, $id) {
        $Bible = Bible::findOrFail($id);
        $enable = $Bible->enable;
        $Bible->uninstall();
        $Bible->install(FALSE, $enable);

        $resp = new \stdClass();
        $resp->success = TRUE;

        if($Bible->hasErrors()) {
            $resp->success = FALSE;
            $resp->errors  = $Bible->getErrors();
        }

        return new Response($resp, 200);
    }

    public function uninstall(Request $request, $id) {
        $Bible = Bible::findOrFail($id);
        $Bible->uninstall();

        $resp = new \stdClass();
        $resp->success = TRUE;

        if($Bible->hasErrors()) {
            $resp->success = FALSE;
            $resp->errors  = $Bible->getErrors();
        }

        return new Response($resp, 200);
    }

    public function test(Request $request, $id) {
        $Bible = Bible::findOrFail($id);
        $Engine = new \App\Engine;
        $Engine->allow_disabled_bibles = TRUE;
        $resp = new \stdClass();
        $resp->success  = FALSE;

        if(!$Bible->installed) {
            $resp->errors = ['Not installed or enabled, so can\'t test!'];
            return new Response($resp, 200);
        }

        // if(!$Bible->enabled) {
        //     $resp->errors = ['Not enabled, so can\'t test!'];
        //     return new Response($resp, 200);
        // }

        // Tests a Bible to make sure it has data
        // Only ONE test has to pass for it to be successful
        $tests = [
            ['label' => 'First Verse', 'ref' => 'Genesis 1:1'],
            ['label' => 'Chapter', 'ref' => 'Psalm 23'],
            ['label' => 'Last verse of OT', 'ref' => 'Malachi 4:6'],
            ['label' => 'First verse of NT', 'ref' => 'Matthew 1:1'],
            ['label' => 'Book', 'ref' => '2 John'],
            ['label' => 'Last Verse', 'ref' => 'Revelation 22:21'],
        ];

        $resp->messages = ['<b>Testing ' . $Bible->name . '</b>'];

        foreach($tests as $test) {
            $Engine->resetErrors();
            $results = $Engine->actionQuery(['reference' => $test['ref'], 'bible' => $Bible->module, 'data_format' => 'minimal']);

            if(!$Engine->hasErrors()) {
                $resp->success = TRUE;
                $resp->messages[] = $test['ref'] . ' (' . $test['label'] . ')';

                foreach($results[$Bible->module] as $verse) {
                    $resp->messages[] = $verse->text;
                }
            }
        }

        if(!$resp->success) {
            $resp->success = FALSE;
            $resp->errors  = $Engine->getErrors();
            $resp->message[] = 'No data found';
        }

        $resp->messages[] = '&nbsp;';
        $resp->messages[] = '&nbsp;';

        return new Response($resp, 200);
    }

    public function export(Request $request, $id) {
        $Bible = Bible::findOrFail($id);
        $data  = $request->toArray();
        $over  = (array_key_exists('overwrite', $data) && $data['overwrite']) ? TRUE : FALSE;
        $Bible->export($over);

        $resp = new \stdClass();
        $resp->success = TRUE;

        if($Bible->hasErrors()) {
            $resp->success = FALSE;
            $resp->errors  = $Bible->getErrors();
        }

        return new Response($resp, 200);
    }

    public function meta(Request $request, $id) {
        $Bible  = Bible::findOrFail($id);
        $data   = $request->toArray();
        $create = (array_key_exists('create_new', $data) && $data['create_new']) ? TRUE : FALSE;
        $Bible->updateMetaInfo($create);

        $resp = new \stdClass();
        $resp->success = TRUE;

        if($Bible->hasErrors()) {
            $resp->success = FALSE;
            $resp->errors  = $Bible->getErrors();
        }

        return new Response($resp, 200);
    }    

    public function revert(Request $request, $id) {
        $Bible  = Bible::findOrFail($id);
        $data   = $request->toArray();
        $Bible->revertMetaInfo();

        $resp = new \stdClass();
        $resp->success = TRUE;

        if($Bible->hasErrors()) {
            $resp->success = FALSE;
            $resp->errors  = $Bible->getErrors();
        }

        return new Response($resp, 200);
    }

    public function research(Request $request, $id) {
        $Bible  = Bible::findOrFail($id);
        $Bible->research = 1;
        $Bible->save();

        $resp = new \stdClass();
        $resp->success = TRUE;

        if($Bible->hasErrors()) {
            $resp->success = FALSE;
            $resp->errors  = $Bible->getErrors();
        }

        return new Response($resp, 200);
    }   

    public function unresearch(Request $request, $id) {
        $Bible  = Bible::findOrFail($id);
        $Bible->research = 0;
        $Bible->save();

        $resp = new \stdClass();
        $resp->success = TRUE;

        if($Bible->hasErrors()) {
            $resp->success = FALSE;
            $resp->errors  = $Bible->getErrors();
        }

        return new Response($resp, 200);
    }

    public function uniqueCheck(Request $request) {
        $data  = $request->toArray();

        $valid_fields = ['name', 'shortname', 'module'];
        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->errors = [];

        if(!array_key_exists('field_name', $data) || !in_array($data['field_name'], $valid_fields)) {
            $resp->success = FALSE;
            $resp->errors[] = 'Invalid or missing \'field_name\' attribute';
        } 
        else {

            $Query = Bible::where($data['field_name'], $data['value'])->where('id', '!=', (int) $data['id']);

            $Bible = $Query->first();

            if($Bible) {
                $resp->success = FALSE;
                $resp->errors[] = 'Duplicate found';
            }
        }

        return new Response($resp, $resp->success ? 200 : 401);
    }

    public function importCheck(Request $request) {
        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->errors  = [];

        $Manager = Helpers::make('\App\ImportManager');
        $data    = $request->all();

        if($Manager->checkImportFile($data)) {
            $resp->bible = $Manager->parsed_attributes ?: [];
            $resp->file  = $Manager->sanitized_filename;
        }
        else {
            $resp->success = FALSE;
            $resp->errors = $Manager->getErrors();
        }

        return new Response($resp, $Manager->getHttpStatus());
    }

    public function import(Request $request) {
        $resp = new \stdClass();
        $resp->success = TRUE;

        $ManagerClass   = Helpers::find('\App\ImportManager');
        $Manager        = new $ManagerClass();

        $rules = $ManagerClass::getImportRules();
        $data  = $request->only(array_keys($rules));

        $v = Validator::make($data, $rules);

        if($v->fails()) {
            $resp->success = FALSE;
            $resp->errors = $v->errors();
            return new Response($resp, 422);
        }

        if($Manager->importFile($data)) {
            $resp->bible = $Manager->parsed_attributes ?: [];
        }
        else {
            $resp->success = FALSE;
            $resp->errors = $Manager->getErrors();
        }

        return new Response($resp, $Manager->getHttpStatus());
    }
}
