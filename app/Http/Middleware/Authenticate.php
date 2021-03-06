<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use App\Http\Responses\Response;

class Authenticate
{
    /**
     * The Guard implementation.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  Guard  $auth
     * @return void
     */
    public function __construct(Guard $auth) {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param integer $access_level minimal access level to view
     * @return mixed
     */
    public function handle($request, Closure $next, $access_level = 1) {
        if ($this->auth->guest()) {

            if ($request->ajax()) {
                $resp = new \stdClass;
                $resp->success = FALSE;
                $resp->message = 'Your session has timed out, please log in again.';
                return new Response($resp, 401);
            }
            else {
                return redirect()->guest('login');
            }
        }

        // Check the user's access level against the minimal
        if($this->auth->user()->access_level < $access_level) {
            if ($request->ajax()) {
                return response('Access Denied', 403);
            }
            else {
                die('access denied');
                // Send authenticated users without the appropiate access level back to the landing page
                return redirect('/landing');
            }
        }

        return $next($request);
    }
}
