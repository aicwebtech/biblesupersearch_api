<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Engine;

class ApiController extends Controller {
    public function query(Request $Request) {
        return $this->sendResponse($Request, 'query');
    }
    
    public function bibles(Request $Request) {
        return $this->sendResponse($Request, 'bibles');
    }
    
    public function books(Request $Request) {
        return $this->sendResponse($Request, 'books');
    }

    private function sendResponse(Request $Request, $action) {
        $input = $Request->input();
        $Engine = new Engine();
        $action_method = 'action' . ucfirst($action);
        //header("Access-Control-Allow-Origin: *"); // Enable for debugging
        $response = new \stdClass();
        $response->errors = array();
        $code = 200;
        
        try {
            $results = $Engine->$action_method($input);
            $response->results = $results;
        } 
        catch (Exception $ex) {
            $resp = $ex->getMessage();
            
            return (new Response($resp, 500))
                -> header('Content-Type', 'application/json; charset=utf-8')
                -> header('Access-Control-Allow-Origin', '*');
        }

        if($Engine->hasErrors()) {
            $errors = $Engine->getErrors();
            $response->errors = $errors;
            $response->error_level = $Engine->getErrorLevel();
            $code = 400;
        }
        else {
            $response = $results; // ?? maintain original data structure if no error??
        }

        if(array_key_exists('callback', $input)) {
            return response()->jsonp($input['callback'], $response);
        }
        
        return (new Response(json_encode($response), $code))
            -> header('Content-Type', 'application/json; charset=utf-8')
            -> header('Access-Control-Allow-Origin', '*');
    }
}
