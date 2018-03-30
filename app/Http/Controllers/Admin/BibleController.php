<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        return view('admin.bibles');
    }

    public function grid(Request $request) {
        $data = $request->toArray();
        $rows = [];
        $rows_per_page = intval($data['rows']);

        $Bibles = Bible::orderBy($data['sidx'], $data['sord'])->paginate($rows_per_page);

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

        $resp = [
            'success' => TRUE,
            'Bible'   => $Bible->attributesToArray()
        ];

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



        $resp = [
            'success' => TRUE,
            'Bible'   => $Bible->attributesToArray()
        ];

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
}
