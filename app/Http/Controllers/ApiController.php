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
        $results = $Engine->$action_method($input);
        
        if($Engine->hasErrors()) {
            $errors = $Engine->getErrors();
            $response = json_encode($errors);
            $code = 400;
        }
        else {
            $response = json_encode($results);
            $code = 200;
        }
        
        return (new Response($response, $code))
            -> header('Content-Type', 'application/json; charset=utf-8')
            -> header('Access-Control-Allow-Origin', '*');
    }
}
