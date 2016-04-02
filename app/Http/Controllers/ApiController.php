<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Engine;

class ApiController extends Controller {
    public function query(Request $Request) {
        $input = $Request->input();
        return $this->sendResponse('query', $input);
    }
    
    public function bibles() {
        return $this->sendResponse('bibles');
    }

    private function sendResponse($action, $input = array()) {
        //var_dump($input);
        $Engine = new Engine();
        $action_method = 'action' . ucfirst($action);
        $results = $Engine->$action_method($input);
        
        if($Engine->hasErrors()) {
            $errors = $Engine->getErrors();
            $errors = json_encode($errors);
            
            return (new Response($errors, 400))
                -> header('Content-Type', 'application/json');
        }
        else {
            $results = json_encode($results);
            
            return (new Response($results, 200))
                -> header('Content-Type', 'application/json');
        }
        
    }
}
