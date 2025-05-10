<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{

    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Http\Middleware\HandleCors::class
    ];

    /**
     * The application's route middleware aliases.
     * Note: In new Laravel options, this is now $middlewareAliases
     * However, attempting to use that name here causes an error
     *
     * @var array
     */
    protected $routeMiddleware  = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'api.access' => \App\Http\Middleware\ApiAccess::class,
        'https' => \App\Http\Middleware\HttpsRedirect::class,
        'install' => \App\Http\Middleware\InstallRedirect::class,
        'installed' => \App\Http\Middleware\InstalledRedirect::class,
        'migrate' => \App\Http\Middleware\CheckMigration::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'dev_tools' => \App\Http\Middleware\CheckDevTools::class,
    ];

    /**
    * The application's route middleware groups.
    *
    * @var array
    */
   protected $middlewareGroups = [
       'web' => [
           \App\Http\Middleware\EncryptCookies::class,
           \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
           \Illuminate\Session\Middleware\StartSession::class,
           \Illuminate\View\Middleware\ShareErrorsFromSession::class,
           \App\Http\Middleware\VerifyCsrfToken::class,
           \Illuminate\Routing\Middleware\SubstituteBindings::class,
       ],

       'api' => [
           'throttle:60,1',
           // 'auth:api',
           'api.access',
       ],     

       'api_testing' => [
           'throttle:6000,1',
           // 'auth:api',
           'api.access',
       ],
   ];

   public function __construct(\Illuminate\Contracts\Foundation\Application $app, \Illuminate\Routing\Router $router) 
   {
       $this->bootstrappers[] = \App\Http\Bootstrap\LoadSoftConfiguration::class;

       parent::__construct($app, $router);
   }
}
