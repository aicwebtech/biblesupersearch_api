<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Responses\Response;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use \App\Models\Bible;
use \App\AudioManager;

class AudioBibleController extends Controller
{
    public function __construct() 
    {
        parent::__construct();
        $this->middleware('auth:100');
    }
    
    public function grid(Request $request, $id)
    {
        $Bible = Bible::find($id);

        if(!$Bible) {
            abort(404, 'Bible not found');
        }

        if(!$Bible->installed) {
            abort(404, 'Bible not installed');
        }

        if(!AudioManager::audioEnabled($Bible)) {
            abort(404, 'Bible has no audio');
        }

        $params = $request->all();
        $params['page'] = isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1;
        $rows_per_page = $request->input('rows_per_page', 10);
        $Verses = $Bible->getAudioAll($params);

        $resp = [
            'total'     => $Verses->lastPage(),
            'page'      => $Verses->currentPage(),
            'rows'      => $Verses->items(),
            'records'   => $Verses->total(),
        ];

        return response($resp, 200);
    }

    public function upload(Request $request)
    {
        $Manager = new AudioManager();

        $files = $request->file('files');
        $overwrite_existing = $request->input('overwrite_existing', '0') == '1' ? true : false;
        $matching = $request->input('matching', 'auto');
        $module = $request->input('module', null);

        if(empty($module) || empty($files)) {
            return response(['error' => 'Invalid parameters'], 400);
        }

        $resp = new \stdClass();
        $resp->success = true;

        $resp->results = $Manager->uploadAudioFiles($module, $files, $matching, $overwrite_existing);

        return new Response($resp, 200);
    }

    public function preview(Request $request)
    {
        $Manager = new AudioManager();

        $files = $request->input('filenames', []);
        $matching = $request->input('matching', 'auto');
        $module = $request->input('module', null);

        if(empty($module) || empty($files)) {
            return response(['error' => 'Invalid parameters'], 400);
        }

        $resp = new \stdClass();
        $resp->success = true;

        $resp->results = $Manager->previewAudioFiles($module, $files, $matching);

        return new Response($resp, 200);
    }
}
