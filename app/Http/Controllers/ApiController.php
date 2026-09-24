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

    /**
     * The actions that hand back a file rather than the JSON envelope, and are POST-only from
     * API v3 on.
     *
     * The legacy versions still answer either method, so existing GET clients are not broken by
     * the new rule. Named here rather than inline because the parameter docs are built from this
     * list - see resources/lang/en/api.php - and the response itself says only 'Action requires
     * POST method', with nothing to point a v2 client at the version behind it.
     */
    public const POST_ONLY_ACTIONS = ['render', 'download'];

    /**
     * The versioned API route - '/api/v{version}/{action?}'.
     *
     * $version is always digits: the route constrains it, so an action whose name begins with
     * 'v' ('version', and anything added later) is never mistaken for a version and falls
     * through to genericAction() by way of the unversioned route.
     */
    public function versionedAction(Request $Request, $version, $action = 'query') 
    {
        $vv = 'v' . $version;

        if(!in_array($vv, config('app.api_version_list'))) {
            // A retired version is told so; a version number that never existed is not.
            // The comparison is on integers - as strings 'v2' <= 'v10' is false, so a lexical
            // check would start answering 404 'not found' for a retired v2 the day a
            // two-digit version exists.
            // Defaulted, not just read: 'api_version_eol' is new, and a deployment still
            // serving a config cache built before it was added resolves NULL here. Passing
            // that to ltrim() is deprecated on PHP 8.1+ and leaves $eol at 0, which answers
            // 404 'not found' for a version that is retired rather than 410.
            $eol = (int) ltrim((string) config('app.api_version_eol', 'v1'), 'v');

            if((int) $version >= 1 && (int) $version <= $eol) {
                return $this->_makeErrorResponse('API version is End of Life and no longer supported: ' . $vv, 410);
            }

            return $this->_makeErrorResponse('API version not found: ' . $vv, 404);
        }
    
        return $this->genericAction($Request, $action, $version);
    }

    public function genericAction(Request $Request, $action = 'query', $version = 2)
    {
        $allowed_actions = ['query', 'bibles', 'books', 'statics', 'statics_changed', 'access', 'version', 'readcache', 'strongs', 'requirements'];

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
            return $this->_makeErrorResponse('Action not found', 404);
        }

        // After the allowed-action check, not before it: on an install with downloads off,
        // answering 405 here would report that a disabled action exists.
        if($version >= 3 && in_array($action, self::POST_ONLY_ACTIONS) && !$Request->isMethod('post')) {
            return $this->_makeErrorResponse('Action requires POST method', 405);
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
                // Just send a generic 500 error message to the client, but log the exception
                \Log::error('API error on action \'' . $action . '\': ' . $ex->getMessage(), ['exception' => $ex]);
                return $this->_makeErrorResponse(__('errors.500'), 500);
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

    /**
     * Answers with an error in the envelope a failed action uses.
     *
     * These paths never reach an engine - the version, the action or the request method is
     * rejected before one is built - so there is no getMetadata() to carry the message, and
     * the body was a bare string under a 'Content-Type: application/json' header. A client
     * calling response.json() threw on it before it could read the message.
     *
     * The level defaults to 4 (fatal): every one of these answers without results.
     *
     * @param string $message
     * @param int $code HTTP status
     * @param int $level Error level, see App\Traits\Error
     * @return \Illuminate\Http\Response
     */
    private function _makeErrorResponse($message, $code, $level = 4)
    {
        $response = new \stdClass();
        $response->errors = [$message];
        $response->error_level = $level;

        return $this->_makeResponse(json_encode($response), $code);
    }

    private function _prettyPrintErrors($input, $response) 
    {
        return view('errors.pretty_print', [
            'input'    => $input,
            'response' => $response,
        ]);
    }
}
