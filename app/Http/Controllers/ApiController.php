<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Engine;

class ApiController extends Controller 
{

    public function genericAction(Request $Request, $action = 'query') 
    {
        $allowed_actions = ['query', 'bibles', 'books', 'statics', 'statics_changed', 'version', 'readcache', 'strongs', 'requirements'];
        
        if(env('APP_ENV', 'production') != 'testing') {
            // header("Access-Control-Allow-Origin: *");
        }

        if(config('download.enable')) {
            $allowed_actions[] = 'render';
            $allowed_actions[] = 'render_needed';
            $allowed_actions[] = 'download';
        }

        $debug_input = FALSE;
        $_SESSION['debug'] = [];

        if(!in_array($action, $allowed_actions)) {
            return $this->_makeResponse('Action not found', 404);
        }

        $input = $Request->input();
        $pretty_print = (array_key_exists('pretty_print', $input) && $input['pretty_print']);
        $Engine = new Engine();
        $actionMethod = 'action' . \Illuminate\Support\Str::studly($action);

        if($debug_input) {
            return $this->_makeResponse(json_encode($input), 200);
        }

        try {
            $results = $Engine->$actionMethod($input);

            if(config('app.debug_query') && $action == 'query') {
                $Engine->addError( '<pre>' . print_r($_SESSION['debug'], TRUE) . '</pre>', 1);
            }

            $response = $Engine->getMetadata(TRUE);
            $response->results = $results;
            $code = ($Engine->hasErrors()) ? 400 : 200;
        }
        catch (Exception $ex) {        
            if( env('APP_ENV', 'production') == 'production') {
                return $this->_makeResponse($ex->getMessage(), 500);
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
