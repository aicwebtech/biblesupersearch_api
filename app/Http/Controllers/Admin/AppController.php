<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Responses\Response;
use App\Models\Bible;
use App\Models\Language;
use App\Helpers;

/** 
 * Controller for the adminstrative single page application (SPA).
*/

class AppController extends Controller
{
    public function __construct() 
    {
        parent::__construct();
        $this->middleware('auth:100');
        $this->middleware(['install', 'migrate'])->except(['statics']);
    }

    /**
     * :todo Display the main admin SPA.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return new Response(501, 'Not Implemented');

        // Init stuff here??
        Bible::updateNeedsUpdate();
        Bible::populateBibleTable();

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

        // End init stuff
    }

    /**
     * Returns the static data for the admin SPA.
     *
     * @return \Illuminate\Http\Response
     */
    public function statics()
    {
        $filter = request()->input('filter', null);

        $response = new \stdClass;
        $response->statics = new \stdClass;
        $response->statics->success = true;

        if($this->includeStatics('languages', $filter)) {
            $response->statics->languages = Language::orderBy('name', 'asc')->get();
        }

        if($this->includeStatics('copyrights', $filter)) {
            $response->statics->copyrights = [];
            
            foreach(\App\Models\Copyright::all() as $Copyright) {
                $data = $Copyright->getAttributes();
                $data['copyright_statement_processed'] = $Copyright->getProcessedCopyrightStatement();
                $response->statics->copyrights[] = $data;
            }
        }

        if($this->includeStatics('importers', $filter)) {
            $ImportManagerClass = Helpers::find('\App\ImportManager');
            $response->statics->importers = $ImportManagerClass::getImportersList();
        }

        if($this->includeStatics('booleans', $filter)) {
            $response->statics->devToolsEnabled  = (bool) config('bss.dev_tools');
            $response->statics->premToolsEnabled = config('app.premium');
        }

        if($this->includeStatics('max_upload_size', $filter)) {
            $response->statics->maxUploadSize = Helpers::maxUploadSize('both');
        }

        return new Response($response, 200);
    }

    /**
     * Indicate whether the given static item should be included in the response.
     * @param string $item
     * @param array|null $filter
     * @return bool
     */
    private function includeStatics($item, $filter)
    {
        if(empty($filter)) {
            return true; // No filter, include everything
        }   

        if(is_array($filter)) {
            return in_array($item, $filter); // Check if item is in the filter array
        } elseif(is_string($filter)) {
            return $item === $filter; // Check if item matches the string filter
        }
    }
}
