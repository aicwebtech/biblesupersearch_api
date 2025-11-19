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
use Validator;

use App\Models\Books\BookAbstract as Book;

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
        // ONLY pull languages WITH BIBLES
        $Languages = Language::join('bibles', 'bibles.lang_short', '=', 'languages.code')
                        ->select('languages.*')
                        ->distinct()
                        ->orderBy('languages.name', 'ASC')
                        ->get();

        // Add book lists, ect for these language if not exists
        foreach($Languages as $Lang) {
            $Lang->initLanguage();
        }

        $bootstrap = new \stdClass();
        // $bootstrap->Languages = $Languages;
        $bootstrap->baseURL = url('');
        $bootstrap->tts_enabled = (bool)config('audio.enable', false) && (bool)config('audio.tts_api_enable', false);
        $bootstrap->tts_apis = \App\AudioManager::getTtsApisList();
        $bootstrap->tts_api_default = config('audio.tts_api') ?? null;

        return view('admin.languages', [
            'bootstrap' => json_encode($bootstrap),
        ]);        
    }

    public function grid(Request $request)
    {
        $data = $request->toArray();

        $rows = $postfilters = [];
        $rows_per_page = (int) $data['rows'];
        $page          = (int) $_REQUEST['page'];
        $show_all = $rows_per_page < 1;

        $pf = DB::getTablePrefix();

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

        $searchable = [
            'code' => [
                'field' => 'languages.code',
                'type'  => 'str_start',
            ],
            'name' => [
                'field' => 'languages.name',
                'type'  => 'str_inside',
            ],
            'native_name' => [
                'field' => 'languages.native_name',
                'type'  => 'str_start',
            ],
            'family' => [
                'field' => 'languages.family',
                'type'  => 'str_inside',
            ],            
            'bibles_min' => [
                'field' => 'bibles',
                'type'  => 'int_min',
            ],            
            'bibles_max' => [
                'field' => 'bibles',
                'type'  => 'int_max',
            ],
        ];

        foreach($searchable as $key => $f) {            
            if(isset($data[$key]) && $data[$key]) {
                
                switch($f['type']) {
                    case 'int_min':
                        $Query->having($f['field'], '>=', (int)$data[$key]);
                        break;        
                    case 'int_max':
                        $Query->having($f['field'], '<=', (int)$data[$key]);
                        break;                
                    case 'str_inside':
                        $Query->where($f['field'], 'LIKE', '%' . $data[$key] . '%');
                        break;
                    case 'str_start':
                    default:
                        $Query->where($f['field'], 'LIKE', $data[$key] . '%');
                }
            }
        }

        $has_post_filter = empty($postfilters) ? FALSE : TRUE;
        $has_file_filter = NULL;

        $Languages = ($has_post_filter || $show_all) ? $Query->get() : $Query->paginate($rows_per_page);

        foreach($Languages as $Language) {
            $row = $Language->getAttributes();
            $row['has_bibles'] = $row['has_bibles'] ? 1 : 0;

            $rows[] = $row;
        }

        if($has_post_filter && !$show_all) {
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
        } elseif($show_all) {
            $count  = count($rows);

            $resp = [
                'total'     => $count,
                'page'      => $page,
                'rows'      => $rows,
                'records'   => $count,
                'post'      => TRUE,
            ];
        } else {
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

    // todo, make a separate controller for book lists
    public function gridBookList(string $lang, Request $request)
    {
        $data = $request->toArray();

        $rows = $postfilters = [];
        $rows_per_page = (int) $data['rows'];
        $page          = (int) $_REQUEST['page'];

        $pf = DB::getTablePrefix();
        $table = 'books_' . $lang;
        $table_en = 'books_en' ;

        $BookClassEn = Book::getClassNameByLanguageStrict('en');
        $BookClass = Book::getClassNameByLanguageStrict($lang);

        if(!\Schema::hasTable($table) || !$BookClass) {
            $resp = [
                'total'     => 0,
                'page'      => 0,
                'rows'      => [],
                'records'   => 0,
                'post'      => FALSE,
                'message'   => 'No book list for this language',
            ];

            return response($resp, 404);
        }

        $Query = $BookClassEn::select($table_en . '.name AS name_en', 'book.*')
                    ->leftJoin($table . ' AS book', 'book.id', $table_en . '.id')
                    ->orderBy( $data['sidx'], $data['sord'] );

        $has_post_filter = empty($postfilters) ? FALSE : TRUE;
        $has_file_filter = NULL;

        $Books =  $Query->paginate($rows_per_page);

        foreach($Books as $Book) {
            $row = $Book->getAttributes();
            $rows[] = $row;
        }

        $resp = [
            'total'     => $Books->lastPage(),
            'page'      => $Books->currentPage(),
            'rows'      => $rows,
            'records'   => $Books->total(),
            'post'      => FALSE,
        ];

        return response($resp, 200);
    }

    public function show($id) 
    {   
        $Language = Language::find($id);

        $resp = new \stdClass();
        $resp->success  = true;
        $resp->Language = $Language->attributesToArray();

        $resp->Language['tts_api_voices'] = \App\TextToSpeech\TtsAbstract::getAllApiVoicesByLanguage($Language->code, $Language->tts_api);

        return new Response($resp, 200);
    }    

    /* 
        Redundant and deprecated, use resource fetch method
     */
    public function fetch($lang) 
    {   
        $Language = Language::findByCode($lang, true);

        $resp = new \stdClass();
        $resp->success  = true;
        $resp->Language = $Language->attributesToArray();

        return new Response($resp, 200);
    }

    // Redundant and deprecated, use resource store method
    public function save(Request $request) 
    {
        throw new \Exception('Deprecated method, use store() instead');
        
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

        return $this->_save($request, null);
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
        } else {
            $Language = new Language();
            $isNew = true;
        }

        $rules = $Language::getUpdateRules($id);
        $data  = $request->only(array_keys($rules));

        // Note: Saving of new languages is basic and not complete
        // New-saving functionality here just used to test editdialog
        if($isNew) {
            $data['code'] = $data['iso_639_3'] = substr(hash('md4', time()), 0, 3);
            $data['iso_name'] = $data['name'];
            $data['native_name'] = $data['name'];
        }

        $v = Validator::make($data, $rules);

        if($v->fails()) {
            $resp->success = FALSE;
            $resp->errors = $v->errors();
            return new Response($resp, 422);
        }

        $Language->fill($data);
        $Language->save();

        $resp->success  = true;
        $resp->Language = $Language->attributesToArray();

        return new Response($resp, 200);
    }
}
