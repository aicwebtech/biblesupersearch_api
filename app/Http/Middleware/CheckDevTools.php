<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class CheckDevTools
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $enabled = config('bss.dev_tools');

        if(!$enabled) {
            $response = new \stdClass;
            $response->errors = array('Dev Tools must be enabled to use this feature');
            $response->success = FALSE;

            return (new Response(json_encode($response), 503))
                -> header('Content-Type', 'application/json; charset=utf-8');
        }

        return $next($request);
    }
}
