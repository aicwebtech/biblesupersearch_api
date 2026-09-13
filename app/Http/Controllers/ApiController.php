<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Engine;
use App\Factories\EngineFactory;

class ApiController extends Controller 
{

    public function versionedAction(Request $Request, $version, $action = 'query') 
    {
        $vv = 'v' . $version;

        $disamb = ['version'];

        if(in_array($vv, $disamb)) {
            // $vv is actually the action, and the version is v2 
            $action = $vv;
            $version = 2;
            $vv = 'v' . $version;
        }
        
        if(!in_array($vv, config('app.api_version_list'))) {
            // A retired version is told so; anything that is not a whole number was never a
            // version at all. Both comparisons are on integers - as strings 'v2' <= 'v10' is
            // false, so a lexical check would start answering 404 'not found' for a retired
            // v2 the day a two-digit version exists.
            $eol = (int) ltrim(config('app.api_version_eol'), 'v');

            if(ctype_digit((string) $version) && (int) $version >= 1 && (int) $version <= $eol) {
                return $this->_makeResponse('API version is End of Life and no longer supported: ' . $vv, 410);
            }

            return $this->_makeResponse('API version not found: ' . $vv, 404);
        }
    
        return $this->genericAction($Request, $action, $version);
    }

    public function genericAction(Request $Request, $action = 'query', $version = 2)
    {
        $allowed_actions = ['query', 'bibles', 'books', 'statics', 'statics_changed', 'version', 'readcache', 'strongs', 'requirements'];

        if(config('download.enable')) {
            $allowed_actions[] = 'render';
            $allowed_actions[] = 'render_needed';
            $allowed_actions[] = 'download';
        }

        if(config('audio.enable')) {
            $allowed_actions[] = 'audio';
            $allowed_actions[] = 'audio_check';
        }

        $debug_input = FALSE;
        $_SESSION['debug'] = [];

        if(!in_array($action, $allowed_actions)) {
            return $this->_makeResponse('Action not found', 404);
        }

        // After the allowed-action check, not before it: on an install with downloads off,
        // answering 405 here would report that a disabled action exists.
        $post_only = ['render', 'download'];

        if($version >= 3 && in_array($action, $post_only) && !$Request->isMethod('post')) {
            return $this->_makeResponse('Action requires POST method', 405);
        }

        $input = $Request->input();
        $pretty_print = (array_key_exists('pretty_print', $input) && $input['pretty_print']);
        $actionMethod = 'action' . \Illuminate\Support\Str::studly($action);

        if($debug_input) {
            return $this->_makeResponse(json_encode($input), 200);
        }

        try {
            // Inside the try: the factory resolves the engine class by name, so a version that
            // is advertised without a matching App\Engines\EngineV{n} raises an \Error here.
            $Engine = EngineFactory::getNewEngine($version);
            $results = $Engine->$actionMethod($input);

            if(config('app.debug_query') && $action == 'query') {
                $Engine->addError( '<pre>' . print_r($_SESSION['debug'], TRUE) . '</pre>', 1);
            }

            $response = $Engine->getMetadata(TRUE);
            $response->results = $results;
            $code = ($Engine->hasErrors()) ? 400 : 200;
        }
        catch (\Throwable $ex) {        
            if( config('app.env') == 'production') {
                // The message goes to the log, not to the client. Until this block caught
                // \Throwable it never ran - the old catch named an unqualified Exception,
                // which resolves to App\Http\Controllers\Exception - so nothing had returned
                // $ex->getMessage() to a caller before. A QueryException's message carries the
                // failing SQL along with the connection's host, port and database name.
                \Log::error('API error on action \'' . $action . '\': ' . $ex->getMessage(), ['exception' => $ex]);

                return $this->_makeResponse(__('errors.500'), 500);
            }

            throw $ex;
        }

        if(array_key_exists('callback', $input)) {
            return response()->jsonp($input['callback'], $response);
        }

        if($Engine->hasErrors() && $pretty_print) {
            return $this->_prettyPrintErrors($input, $response);
        }

        return $this->_makeResponse(json_encode($response), $code);
    }

    private function _makeResponse($content, $code)
    {
        return (new Response($content, $code))
            -> header('Content-Type', 'application/json; charset=utf-8')
            -> header('Access-Control-Allow-Origin', '*');
    }

    private function _prettyPrintErrors($input, $response) 
    {
        return view('errors.pretty_print', [
            'input'    => $input,
            'response' => $response,
        ]);
    }
}
