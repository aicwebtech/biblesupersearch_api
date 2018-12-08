<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use App\InstallManager;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        HttpException::class,
        ModelNotFoundException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        return parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if ($e instanceof ModelNotFoundException) {
            $e = new NotFoundHttpException($e->getMessage(), $e);
        }

        if ($e instanceof TokenMismatchException){
            if(!InstallManager::isInstalled()) {
                return redirect(route('admin.install'))->with('csrf_error',"Your session timed out. Please try again.");
            }

            // Redirect to a form. Here is an example of how I handle mine
            return redirect($request->fullUrl())->with('csrf_error',"Your session timed out. Please try again.");
        }

        return parent::render($request, $e);
    }

    /**
    * Convert an authentication exception into an unauthenticated response.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  \Illuminate\Auth\AuthenticationException  $exception
    * @return \Illuminate\Http\Response
    */
   protected function unauthenticated($request, AuthenticationException $exception)
   {
       if ($request->expectsJson()) {
           return response()->json(['error' => 'Unauthenticated.'], 401);
       }

       return redirect()->guest('login');
   }
}

  // Declaration of App\Exceptions\Handler::unauthenticated($request, App\Except  
  // ions\AuthenticationException $exception) should be compatible with Illumina  
  // te\Foundation\Exceptions\Handler::unauthenticated($request, Illuminate\Auth  
  // \AuthenticationException $exception)      
