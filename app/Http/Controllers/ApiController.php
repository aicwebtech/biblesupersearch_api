<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Engine;

class ApiController extends Controller {

    public function genericAction($action = 'query', Request $Request) {
        $allowed_actions = ['query', 'bibles', 'books', 'statics', 'version'];

        if(!in_array($action, $allowed_actions)) {
            return new Response('Action not found', 404);
        }

        $input = $Request->input();
        $Engine = new Engine();
        $actionMethod = 'action' . ucfirst($action);
        //header("Access-Control-Allow-Origin: *"); // Enable for debugging
        $response = new \stdClass();
        $response->errors = array();
        $response->error_level = 0;
        $code = 200;

        try {
            $results = $Engine->$actionMethod($input);
            $response->results = $results;
        }
        catch (Exception $ex) {
            return (new Response($ex->getMessage(), 500))
                -> header('Content-Type', 'application/json; charset=utf-8')
                -> header('Access-Control-Allow-Origin', '*');
        }

        if($Engine->hasErrors()) {
            $errors = $Engine->getErrors();
            $response->errors = $errors;
            $response->error_level = $Engine->getErrorLevel();
            $code = 400;
        }

        if(array_key_exists('callback', $input)) {
            return response()->jsonp($input['callback'], $response);
        }

        return (new Response(json_encode($response), $code))
            -> header('Content-Type', 'application/json; charset=utf-8')
            -> header('Access-Control-Allow-Origin', '*');
    }
}
