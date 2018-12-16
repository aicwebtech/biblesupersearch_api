<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
//use Illuminate\Http\Response;
use App\Http\Responses\Response;
use App\Http\Controllers\Controller;
use App\Models\Bible;

class BibleController extends Controller
{
    public function __construct() {
        parent::__construct();
        $this->middleware('auth:100');
    }

    /**
     * Display a listing of the resource.
     * In this case, a page with a jqGrid
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        Bible::populateBibleTable();
        return view('admin.bibles');
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
    }

    protected function _save(Request $request, $id = NULL) {
        if($id) {
            $Bible = Bible::findOrFail($id);
        }
        else {
            $Bible = new Bible();
        }

        $resp = new \stdClass();
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
        $resp = new \stdClass();
        $resp->success  = FALSE;

        if(!$Bible->installed) {
            $resp->errors = ['Not installed or enabled, so can\'t test!'];
            return new Response($resp, 200);
        }

        if(!$Bible->enabled) {
            $resp->errors = ['Not enabled, so can\'t test!'];
            return new Response($resp, 200);
        }

        // Tests a Bible to make sure it has data
        // Only ONE test has to pass for it to be successful
        $tests = [
            ['label' => 'First Verse', 'ref' => 'Genesis 1:1'],
            ['label' => 'Chapter', 'ref' => 'Psalm 23'],
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
}
