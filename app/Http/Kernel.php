<?php

namespace aicwebtech\BibleSuperSearch\Http;

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
        \aicwebtech\BibleSuperSearch\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \aicwebtech\BibleSuperSearch\Http\Middleware\VerifyCsrfToken::class,
    ];

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \aicwebtech\BibleSuperSearch\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'guest' => \aicwebtech\BibleSuperSearch\Http\Middleware\RedirectIfAuthenticated::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'api.access' => \aicwebtech\BibleSuperSearch\Http\Middleware\ApiAccess::class,
        'https' => \aicwebtech\BibleSuperSearch\Http\Middleware\HttpsRedirect::class,
        'install' => \aicwebtech\BibleSuperSearch\Http\Middleware\InstallRedirect::class,
        'installed' => \aicwebtech\BibleSuperSearch\Http\Middleware\InstalledRedirect::class,
        'migrate' => \aicwebtech\BibleSuperSearch\Http\Middleware\CheckMigration::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'dev_tools' => \aicwebtech\BibleSuperSearch\Http\Middleware\CheckDevTools::class,
    ];

    /**
    * The application's route middleware groups.
    *
    * @var array
    */
   protected $middlewareGroups = [
       'web' => [
           \aicwebtech\BibleSuperSearch\Http\Middleware\EncryptCookies::class,
           \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
           \Illuminate\Session\Middleware\StartSession::class,
           \Illuminate\View\Middleware\ShareErrorsFromSession::class,
           \aicwebtech\BibleSuperSearch\Http\Middleware\VerifyCsrfToken::class,
           \Illuminate\Routing\Middleware\SubstituteBindings::class,
       ],

       'api' => [
           'throttle:60,1',
           // 'auth:api',
           'api.access',
       ],
   ];

   public function __construct(\Illuminate\Contracts\Foundation\Application $app, \Illuminate\Routing\Router $router) {
       $this->bootstrappers[] = \aicwebtech\BibleSuperSearch\Http\Bootstrap\LoadSoftConfiguration::class;

       parent::__construct($app, $router);
   }
}
