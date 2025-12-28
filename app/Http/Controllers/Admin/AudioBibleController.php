<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use \App\Models\Bible;

class AudioBibleController extends Controller
{
    public function __construct() 
    {
        parent::__construct();
        $this->middleware('auth:100');
        // $this->middleware('dev_tools')->only('export', 'meta');
    }
    
    public function grid(Request $request, $id)
    {
        $Bible = Bible::find($id);

        if(!$Bible) {
            abort(404, 'Bible not found');
        }

        $params = $request->all();
        // $params['has_audio'] = 0;
        $params['page'] = isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1;
        $rows_per_page = $request->input('rows_per_page', 10);
        $Verses = $Bible->getAudioAll($params);

        // print_r($Verses); die();

        $resp = [
            'total'     => $Verses->lastPage(),
            'page'      => $Verses->currentPage(),
            'rows'      => $Verses->items(),
            'records'   => $Verses->total(),
        ];

        return response($resp, 200);
    }

    public function upload()
    {
        //
    }
}
