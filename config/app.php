<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    |
    */

    'name' => 'Bible SuperSearch API',

    'name_static' => 'Bible SuperSearch API', // DO NOT CHANGE


    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    |
    */

    'version' => '6.3.0.alpha2',


    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    |
    */

    'api_version' => 'v2',          // The current API version for this application

    'api_version_list' => ['v2'],   // List of all API versions supported by this application

    /*
    |--------------------------------------------------------------------------
    | Application Version Cache
    |
    | Soft config holding the last known application version.  
    |
    | When this does NOT equal the application version, an update is triggered 
    |
    |--------------------------------------------------------------------------
    |
    |
    */

    'version_cache' => '4.0.0', // DO NOT CHANGE

    /*
    |--------------------------------------------------------------------------
    | Application Premium Cache
    |
    | When a request is made, we check to see if we have the 'Premium' plugin
    | code and cache the results here.  
    |
    | Note: This value is automatically set.  Forcing this value to TRUE will 
    | not magically grant you premium features, but may instead cause breakage.
    |
    |--------------------------------------------------------------------------
    |
    |
    */

    'premium' => FALSE,

    /*
    |--------------------------------------------------------------------------
    | Application Premium Disable
    |
    | Force disable all premium features even if the code is present.
    |
    | Intended for debugging, must be FALSE in production
    |
    |--------------------------------------------------------------------------
    |
    |
    */

    'premium_disabled' => env('PREMIUM_DISABLED', FALSE),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => env('APP_DEBUG', FALSE),
    
    'debug_query' => env('APP_DEBUG_QUERY', FALSE),

    'env' => env('APP_ENV', 'production'),

    'experimental' => env('APP_EXPERIMENTAL', false),

    'test_http' => env('APP_TEST_HTTP', FALSE),

    'config_cache' => FALSE,

    'installed' => FALSE,

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'url_env' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => env('APP_TIMEZONE','America/Detroit'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => env('DEFAULT_LANGUAGE_SHORT', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY', 'SomeRandomString'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log settings for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Settings: "single", "daily", "syslog", "errorlog"
    |
    */

    'log' => env('APP_LOG', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => [

        'App'       => Illuminate\Support\Facades\App::class,
        'Artisan'   => Illuminate\Support\Facades\Artisan::class,
        'Auth'      => Illuminate\Support\Facades\Auth::class,
        'Blade'     => Illuminate\Support\Facades\Blade::class,
        'Bus'       => Illuminate\Support\Facades\Bus::class,
        'Cache'     => Illuminate\Support\Facades\Cache::class,
        'Config'    => Illuminate\Support\Facades\Config::class,
        'Cookie'    => Illuminate\Support\Facades\Cookie::class,
        'Crypt'     => Illuminate\Support\Facades\Crypt::class,
        'DB'        => Illuminate\Support\Facades\DB::class,
        'Eloquent'  => Illuminate\Database\Eloquent\Model::class,
        'Event'     => Illuminate\Support\Facades\Event::class,
        'File'      => Illuminate\Support\Facades\File::class,
        'Gate'      => Illuminate\Support\Facades\Gate::class,
        'Hash'      => Illuminate\Support\Facades\Hash::class,
        'Input'     => Illuminate\Support\Facades\Input::class,
        'Lang'      => Illuminate\Support\Facades\Lang::class,
        'Log'       => Illuminate\Support\Facades\Log::class,
        'Mail'      => Illuminate\Support\Facades\Mail::class,
        'Notification' => Illuminate\Support\Facades\Notification::class,
        'Password'  => Illuminate\Support\Facades\Password::class,
        'Queue'     => Illuminate\Support\Facades\Queue::class,
        'Redirect'  => Illuminate\Support\Facades\Redirect::class,
        'Redis'     => Illuminate\Support\Facades\Redis::class,
        'Request'   => Illuminate\Support\Facades\Request::class,
        'Response'  => Illuminate\Support\Facades\Response::class,
        'Route'     => Illuminate\Support\Facades\Route::class,
        'Schema'    => Illuminate\Support\Facades\Schema::class,
        'Session'   => Illuminate\Support\Facades\Session::class,
        'Storage'   => Illuminate\Support\Facades\Storage::class,
        'URL'       => Illuminate\Support\Facades\URL::class,
        'Validator' => Illuminate\Support\Facades\Validator::class,
        'View'      => Illuminate\Support\Facades\View::class,

    ],

    /* Whether to use named placeholders for the primary query. *
     * Do not change, or things may / will break!
     */
    'query_use_named_placeholders' => TRUE,

    /* Force every request to https.
     *
     * Off by default: this middleware is global, so an existing deployment that
     * serves plain http would become unreachable. Operators opt in with
     * REDIRECT_HTTPS. Behind a TLS-terminating proxy, configure
     * 'trusted_proxies' below as well; until it is set the middleware declines
     * to redirect a request forwarded as https, since that redirect could only
     * loop, and logs what to configure instead.
     *
     * Note for existing installs: the docs page used to force https on its own
     * (a 'https' route middleware alias with this setting defaulting to on). It
     * no longer does unless REDIRECT_HTTPS is set.
     *
     * Normalised to a real bool rather than left to env(): Laravel's
     * Env::getOption() only maps 'true'/'false'/'null'/'empty' to scalars, so
     * REDIRECT_HTTPS=1 (or yes/on) would otherwise stay the *string* "1" --
     * truthy enough to mark the session cookie Secure (see config/session.php)
     * while failing the strict === TRUE test in HttpsRedirect, which is exactly
     * the mismatch that locks an operator out of the admin login.
     */
    'redirect_https' => filter_var(env('REDIRECT_HTTPS', FALSE), FILTER_VALIDATE_BOOLEAN),

    /* Proxies whose X-Forwarded-* headers may be trusted.
     *
     * Required when TLS is terminated upstream (load balancer, nginx,
     * Cloudflare), otherwise the forwarded scheme is ignored and
     * Request::secure() is permanently FALSE -- REDIRECT_HTTPS then cannot
     * redirect such a request without looping, so HttpsRedirect serves it as-is
     * and logs a warning until this is set.
     * Accepts a comma separated list of IPs/CIDRs. NULL trusts nothing, which is
     * the default.
     *
     * '*' is also accepted, but read this first: Laravel resolves it to the IP
     * that opened the connection -- see setTrustedProxyIpAddressesToTheCallingIp()
     * in Illuminate\Http\Middleware\TrustProxies -- so whoever is talking to the
     * application is trusted, not one known proxy. If the origin is reachable
     * directly, any client can then set
     * X-Forwarded-For and X-Forwarded-Proto to whatever it likes: IP-based daily
     * limits are attributed to a forged address, and Request::secure() reports
     * TRUE over plain http, which silently satisfies REDIRECT_HTTPS above.
     * Only use it when the origin accepts connections from the proxy alone
     * (firewall, private network, or a socket the proxy owns). Otherwise name the
     * proxy's addresses explicitly.
     */
    'trusted_proxies' => env('TRUSTED_PROXIES', NULL),

    'client_url' => env('CLIENT_URL', NULL),
];
