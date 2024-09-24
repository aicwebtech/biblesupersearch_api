<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Responses\Response;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Language;
use App\Models\Shortcuts\ShortcutAbstract;

class LanguageConfigController extends Controller
{
    public function __construct() 
    {
        parent::__construct();
        $this->middleware('auth:100');
        $this->middleware('migrate');
    }

    public function index() 
    {
        $Languages = Language::join('bibles', 'bibles.lang_short', '=', 'languages.code')
                        ->select('languages.*')
                        ->distinct()
                        ->orderBy('languages.name', 'ASC')
                        ->get();

        $Post = Post::where('key', 'tos')->firstOrFail();

        return view('admin.languages_new', [
            'Languages' => $Languages,
            'bootstrap' => true,
        ]);        

        // return view('admin.languages', [
        //     'Languages' => $Languages,
        //     'bootstrap' => true,
        // ]);
    }

    // todo, have an actual grid here
    public function grid(Request $request)
    {
        $data = $request->toArray();
        $rows = $postfilters = [];
        $rows_per_page = (int) $data['rows'];
        $page          = (int) $_REQUEST['page'];

        if($data['sidx'] == 'lang') {
            $data['sidx'] = 'languages.name';
        }        
        else if($data['sidx'] == 'copy') {
            $data['sidx'] = 'copyrights.name';
        }
        else {
            $data['sidx'] = 'bibles.' . $data['sidx'];
        }

        $Query = Bible::select('bibles.*', 'languages.name AS lang', 'copyrights.name AS copy')
            ->leftJoin('languages', 'bibles.lang_short', 'languages.code')
            ->leftJoin('copyrights', 'bibles.copyright_id', 'copyrights.id')
            ->orderBy($data['sidx'], $data['sord']);

        // if(array_key_exists('_search', $data) && $data['_search'] == 'true') {
        //     Helpers::buildGridSearchQuery($data, $Query, [
        //         'lang' => 'bibles.lang_short', 
        //         'copy' => 'bibles.copyright_id', 
        //         'name' => 'bibles.name',
        //         'rank' => 'bibles.rank',
        //         'has_module_file' => 'POSTFILTER',
        //     ]);
            
        //     $postfilters = $data['_post_filters'];
        // }

        $has_post_filter = empty($postfilters) ? FALSE : TRUE;
        $has_file_filter = NULL;

        if(array_key_exists('has_module_file', $postfilters) && $postfilters['has_module_file'] != '_no_rest_') {
            $has_file_filter = (int) $postfilters['has_module_file'];
        }

        $Bibles = ($has_post_filter) ? $Query->get() : $Query->paginate($rows_per_page);

        foreach($Bibles as $Bible) {
            $row = $Bible->getAttributes();
            unset($row['description']);
            $row['has_module_file'] = $Bible->hasModuleFile() ? 1 : 0;
            $row['needs_update']    = $Bible->needsUpdate()   ? 1 : 0;

            if($has_file_filter === 1 && $row['has_module_file'] == 0 || $has_file_filter === 0 && $row['has_module_file'] == 1) {
                continue;
            }

            $rows[] = $row;
        }

        if($has_post_filter) {
            $page   = ($page < 1) ? 1 : $page;
            $offset = $rows_per_page * ($page - 1);
            $count  = count($rows);
            $rows   = array_slice($rows, $offset, $rows_per_page);

            $resp = [
                'total'     => ceil($count / $rows_per_page),
                'page'      => $page,
                'rows'      => $rows,
                'records'   => $count,
                'post'      => TRUE,
            ];
        }
        else {
            $resp = [
                'total'     => $Bibles->lastPage(),
                'page'      => $Bibles->currentPage(),
                'rows'      => $rows,
                'records'   => $Bibles->total(),
                'post'      => FALSE,
            ];
        }

        return response($resp, 200);
    }

    public function fetch($lang) 
    {   
        $Language = Language::findByCode($lang, true);

        $resp = new \stdClass();
        $resp->success  = true;
        $resp->Language = $Language->attributesToArray();

        return new Response($resp, 200);
    }

    public function save(Request $request) 
    {
        $lang = $request->input('language');
        $Language = Language::findByCode($lang, true);

        $Language->common_words = $request->input('common_words');
        $Language->save();

        $resp = new \stdClass();
        $resp->success  = true;
        $resp->Language = $Language->attributesToArray();

        return new Response($resp, 200);
    }

}
