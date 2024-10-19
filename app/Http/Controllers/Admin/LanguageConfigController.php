<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Responses\Response;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Language;
use App\Models\Bible;
use App\Models\Shortcuts\ShortcutAbstract;
use Illuminate\Support\Facades\DB;

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

        // if($data['sidx'] == 'lang') {
        //     $data['sidx'] = 'languages.name';
        // }        
        // else if($data['sidx'] == 'copy') {
        //     $data['sidx'] = 'copyrights.name';
        // }
        // else {
        //     $data['sidx'] = 'bibles.' . $data['sidx'];
        // }

        // $Query = Bible::select('bibles.*', 'languages.name AS lang', 'copyrights.name AS copy')
        //     ->leftJoin('languages', 'bibles.lang_short', 'languages.code')
        //     ->leftJoin('copyrights', 'bibles.copyright_id', 'copyrights.id')
        //     ->orderBy($data['sidx'], $data['sord']);

        $pf = DB::getTablePrefix();

        // todo - rebuild this to use raw query
        // $Query = Language::select('languages.*', DB::raw('COUNT(bibles.id) AS bible') )
        // $Query = Language::select('languages.*', 'COUNT(bibles.id) AS bible')
        $Query = Language::select('languages.*', 'bibles.id AS has_bibles', 
                                    DB::raw('IF(' . $pf . 'book_list.value = 1,1,0) AS book_list'),
                                    DB::raw('COUNT(' . $pf . 'bibles.id) AS bibles')

                                )
                    ->leftJoin('bibles', 'bibles.lang_short', 'languages.code')
                    ->leftJoin('language_attr AS book_list', function($join) {
                        $join -> on('book_list.code', 'languages.code')
                              -> where('book_list.attribute', 'book_list');
                    })
                    ->groupBy('languages.id')
                    ->orderBy( $data['sidx'], $data['sord'] );

        if(!isset($data['all_languages']) || !$data['all_languages']) {
            $Query->whereNotNull('bibles.id');
        }

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

        $Languages = ($has_post_filter) ? $Query->get() : $Query->paginate($rows_per_page);

        foreach($Languages as $Language) {
            $row = $Language->getAttributes();
            $row['has_bibles'] = $row['has_bibles'] ? 1 : 0;

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
                'total'     => $Languages->lastPage(),
                'page'      => $Languages->currentPage(),
                'rows'      => $rows,
                'records'   => $Languages->total(),
                'post'      => FALSE,
            ];
        }

        return response($resp, 200);
    }

    public function show($id) 
    {   
        $Language = Language::find($id);

        $resp = new \stdClass();
        $resp->success  = true;
        $resp->Language = $Language->attributesToArray();

        return new Response($resp, 200);
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) 
    {
        return new Response(null, 501);

        $this->_save($request, null);
    }

    /**
     * Update the specified resource in storage.
     * Use PUT verb
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) 
    {
        return $this->_save($request, $id);
    }

    protected function _save(Request $request, $id = null) 
    {
        $resp = new \stdClass();

        if($id) {
            $Language = Language::findOrFail($id);
            $isNew = false;
        }
        else {
            $Language = new Language();
            $isNew = true;
        }

        $safe = ['name', 'native_name', 'common_words'];

        $data = $request->only($safe);

        // Note: Saving of new languages is basic and not complete
        // New-saving functionality here just used to test editdialog
        if($isNew) {
            $data['code'] = $data['iso_639_3'] = substr(hash('md4', time()), 0, 3);
            $data['iso_name'] = $data['name'];
            $data['native_name'] = $data['name'];
        }

        // $rules = $Language::getUpdateRules($id);
        // $data  = $request->only(array_keys($rules));

        // $v = Validator::make($data, $rules);

        // if($v->fails()) {
        //     $resp->success = FALSE;
        //     $resp->errors = $v->errors();
        //     return new Response($resp, 422);
        // }

        $Language->fill($data);
        $Language->save();

        $resp->success  = true;
        $resp->Language = $Language->attributesToArray();

        return new Response($resp, 200);
    }
}
