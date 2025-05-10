<?php

namespace App;
use Illuminate\Http\Request;
use App\Models\Bible;
use App\Models\Language;
use App\Models\LanguageAttr;
use Artisan;
use App\ConfigManager;

/**
 * Description of InstallManager
 *
 * @author Computer
 */
class InstallManager 
{
    static protected $checklist = [
        'php_version' => null, // Pulled from composer.json
        'php_extensions_required' => [
            'OpenSSL',
            'PDO',
            'Mbstring',
            'Tokenizer',
            'XML',
            'Zip',
            'Ctype',
            'JSON',
            'BCMath',
            'gd',
            'Fileinfo',
            'SQLite3',
        ],
        'php_extensions_recommended' => [],
        'database' => [
            'mysql'  => 'MySQL',
            // 'sqlite' => 'SQLite', // not supported
            // 'sqlsrv' => 'Microsoft SQL Server / SQL Azure', // not supported
            // 'pgsql'  => 'PostgreSQL' // not supported
        ],

        // These directories and files need to be writable
        'writable' => [
            'storage/app', 
            'storage/framework', 
            'storage/logs', 
            'storage/logs/laravel.log', 
            'bootstrap/cache', 
            'bibles', 
            'bibles/modules', 
            'bibles/unofficial', 
            'bibles/rendered', 
            'bibles/misc',
            // 'public/index.php', // For future use (Automatic Upgrades)
        ]
    ];

    static protected $checklist_init = false;
    
    static function getChecklist() 
    {
        if(!static::$checklist_init) {
            static::$checklist_init = true;

            // Read in composer settings
            $composer_txt = file_get_contents(base_path() . '/composer.json');
            $composer     = json_decode($composer_txt);
            static::$checklist['php_version'] = substr($composer->require->php, 2);
        }
        
        
        return static::$checklist;
    }
    
    static function isInstalled() 
    {
        if(config('app.installed')) {
            return TRUE;
        }

        return FALSE;
    }

    static function modRewriteEnabled()
    {
        $enabled = true;

        if(function_exists('apache_get_modules')) {
            $enabled = in_array('mod_rewrite', apache_get_modules());
        } 

        return $enabled;
    }

    static function install(Request $request) 
    {
        $start_time = time();

        // Ensures that this installer can run even when not on CLI
        if(!defined('STDIN')) {
            define('STDIN', fopen('php://stdin', 'r'));
        }

        $ep = error_reporting();

        error_reporting(E_ERROR | E_PARSE); // Workaround for deprecation warning

        // Generate application key
        Artisan::call('key:generate');

        // Set up database // --force Allows migration to run in production
        $exit_code = Artisan::call('migrate', array('--force' => TRUE));
        // $exit_code = Artisan::call('migrate', array('--seed' => TRUE, '--force' => TRUE));

        // Populate the Bible table
        Bible::populateBibleTable();

        // Add admin user
        $User = User::create([
            'name'          => $request->get('name'),
            'username'      => $request->get('username'),
            'email'         => $request->get('email'),
            'password'      => bcrypt( $request->get('password') ),
            'access_level'  => 100,
        ]);

        $User->access_level = 100;
        $User->save();

        // Set 'installed' config
        ConfigManager::setConfigs(['app.installed' => TRUE]);

        // Set Application URL
        $server_url = static::getServerUrl();
        ConfigManager::setConfigs(['app.url' => $server_url]);

        // Set Application Email (System Mail Address)
        ConfigManager::setConfigs(['mail.from.address' => $request->get('email')]);

        $elapsed_time = time() - $start_time;

        if($elapsed_time < 90) {
            // Install default Bible (usally KJV)
            $Bible = Bible::findByModule( config('bss.defaults.bible') );
            $Bible->install(FALSE, TRUE);
        }

        error_reporting($ep);

        return TRUE;
    }

    static function checkSettings() 
    {
        $checklist_raw = static::getChecklist();
        
        // Check PHP version
        $php_version = $checklist_raw['php_version'];
        $php_success = (version_compare(phpversion(), $checklist_raw['php_version'], '>=') == -1);
        $conname = config('database.default');
        $db_info = config('database.connections.' . $conname);
        $sqlite_required = ($db_info['driver'] == 'sqlite');

        $checklist = [];
        $installed_php_parts = explode('.', PHP_VERSION);
        $installed_php = $installed_php_parts[0] . '.' . $installed_php_parts[1] . '.' . intval($installed_php_parts[2]);


        // TODO - MAKE SURE SENDMAIL IS INSTALLED!

        $checklist[] = ['type' => 'header', 'label' => 'Configuration'];
        $env = (is_file(base_path('.env')) && is_writable(base_path('.env')));
        $checklist[] = ['type' => 'item', 'label' => '.env config file exists and is writable', 'success' => $env];
        
        // TODO - detect dedicated (sub) domain!
        $host = request()->getHost();

        $subdomain = $subdomain_pub_dir = false;
        $uri = $_SERVER['REQUEST_URI'];
        $uri_parts = explode('/', trim($uri, '/'));

        if($uri_parts[0] == 'public') {
            $subdomain = true;
            $subdomain_pub_dir = false;
        }
        else if(in_array('public', $uri_parts)) {
            $subdomain = false;
            $subdomain_pub_dir = false;
        } else {
            $subdomain = true;
            $subdomain_pub_dir = true;
        }

        $has_domain_error = (!$subdomain || !$subdomain_pub_dir);
        $has_domain_error = false;

        $subdomain_pub_dir = $subdomain ? $subdomain_pub_dir : null;

        $allowed_uri = [
            '/install/check'
        ];

        $checklist[] = ['type' => 'item', 'label' => 'Has dedicated domain or sub-domain (HIGHLY recommended!)', 'success' => $subdomain ?: null];
        
        if($subdomain) {
            $checklist[] = ['type' => 'item', 'label' => 'Dedicated domain or sub-domain pointed to public directory in API', 'success' => $subdomain_pub_dir];
        }

        // if(!$subdomain) {
        //     $checklist[] = ['type' => 'error', 'label' => 'You appear to have the API running inside your main website.  This won\'t work, and you will need to set up a dedicated sub-domain.'];
        //     $checklist[] = ['type' => 'error', 'label' => 'Please be sure to point your sub-domain to path/to/api/public'];
        // }
    
        if($subdomain && !$subdomain_pub_dir) {
            $checklist[] = ['type' => 'error', 'label' => 'Your sub-domain needs to be pointed to the public directory inside the API.'];
            $checklist[] = ['type' => 'error', 'label' => 'Please point your sub-domain to path/to/api/public NOT path/to/api!'];
        }

        if($has_domain_error) {
            $checklist[] = ['type' => 'error', 'label' => 'You will notice that this page isn\'t formatted nicely, along with all kinds of errors in the console.'];
            $checklist[] = ['type' => 'error', 'label' => 'Please configure the sub-domain properly, and these issues will resolve themselves!'];
        }

        $checklist[] = ['type' => 'hr'];


        $checklist[] = ['type' => 'header', 'label' => 'Software'];
        $checklist[] = ['type' => 'item', 'label' => 'PHP Version >= ' . $php_version . ' (Current = ' . $installed_php . ')', 'success' => $php_success];

        // $extensions = ['OpenSSL', 'PDO', 'Mbstring', 'Tokenizer', 'XML', 'Zip', 'Ctype', 'JSON', 'BCMath', 'gd', 'Fileinfo'];
        // $rec_extensions = [];

        $req_extensions = $checklist_raw['php_extensions_required'];
        $rec_extensions = $checklist_raw['php_extensions_recommended'];

        sort($req_extensions);
        sort($rec_extensions);

        foreach($req_extensions as $ext) {
            $checklist[] = ['type' => 'item', 'label' => 'PHP Extension: ' . $ext, 'success' => extension_loaded($ext)];
        }

        foreach($rec_extensions as $ext) {
            $checklist[] = ['type' => 'item', 'label' => 'PHP Extension: ' . $ext . ' (recommended)', 'success' => extension_loaded($ext) ?: NULL];
        }

        $checklist[] = ['type' => 'hr'];
        $checklist[] = ['type' => 'header', 'label' => 'Database'];

        if(empty($db_info)) {
            $checklist[] = ['type' => 'item', 'label' => 'Unknown DB_CONNECTION: ' . $conname, 'success' => FALSE];
        }

        $pdo_driver = 'PDO_' . strtoupper($db_info['driver']);

        $db_type_map = $checklist_raw['database'];

        if(isset($db_type_map[$db_info['driver']])) {
            $checklist[] = ['type' => 'item', 'label' => 'Selected Database Type: ' . $db_type_map[$db_info['driver']], 'success' => TRUE];
        }
        else {
            $checklist[] = ['type' => 'item', 'label' => 'Unsupported Database Type: ' . $db_info['driver'], 'success' => FALSE];
        }

        $file = ($db_info['driver'] == 'sqlite');


        $checklist[] = ['type' => 'item', 'label' => 'Database PDO Driver: ' . $pdo_driver, 'success' => extension_loaded($pdo_driver)];

        // attempt to connect to db
        $able_to_connect = TRUE;
        $db_connect_msg = null;

        try {
            \DB::connection()->getPdo();

            if($db_info['driver'] == 'sqlite') {
                $rows = \DB::select('SELECT * FROM sqlite_master');
            }
        }
        catch (\Exception $e) {
            $able_to_connect = FALSE;
            $msg = $e->getMessage();
            
            // User-friendly unable-to-connect messages
            $db_connect_msg = '';

            if(stripos($msg, 'unknown database') !== false) {
                $db_connect_msg .= 'Unknown database "' . $db_info['database'] . '"';
            }
            else if(stripos($msg, 'access denied') !== false) {
                $db_connect_msg .= 'Access denied: DB_USERNAME and / or DB_PASSWORD are incorrect.';
            }            
            else if(stripos($msg, 'Name or service not known') !== false) {
                $db_connect_msg .= 'Unable to find DB_HOST of "' . $db_info['host'] . '"';
            }            
            else if(stripos($msg, 'Connection timed out') !== false) {
                $db_connect_msg .= 'Connection timed out; Is your DB_HOST correct?';
            }
            else {
                $db_connect_msg = 'Error Recieved: ' . $msg;
            }
        }

        if(!$file) {
            $checklist[] = ['type' => 'item', 'label' => 'DB_HOST ('. $db_info['host'] . ')', 'success' => (!empty($db_info['host'] && $able_to_connect) || $file)];
            $checklist[] = ['type' => 'item', 'label' => 'DB_DATABASE ('. $db_info['database'] . ')', 'success' => (!empty($db_info['database'] && $able_to_connect) || $file)];
            $checklist[] = ['type' => 'item', 'label' => 'DB_USERNAME ('. $db_info['username'] . ')', 'success' => (!empty($db_info['username'] && $able_to_connect) || $file)];
            $checklist[] = ['type' => 'item', 'label' => 'DB_PASSWORD ('. $db_info['password'] . ')', 'success' => (!empty($db_info['password'] && $able_to_connect) || $file)];
        }
        else {
            $db_file = database_path('database.' . $db_info['driver']);
            $db_file_writable = is_writable($db_file);
            $db_dir = database_path();
            $db_dir_writable = is_writable($db_dir);
            $checklist[] = ['type' => 'item', 'label' => 'DB file is writable: ' . $db_file, 'success' => $db_file_writable];
            $checklist[] = ['type' => 'item', 'label' => 'DB directory is writable: ' . $db_dir, 'success' => $db_dir_writable];
        }

        $checklist[] = ['type' => 'item', 'label' => 'DB_PREFIX ('. $db_info['prefix'] . ')', 'success' => (!empty($db_info['prefix'])) ? TRUE : NULL];


        $checklist[] = ['type' => 'item', 'label' => 'Able to Connect', 'success' => $able_to_connect];

        if(!$able_to_connect) {
            $checklist[] = ['type' => 'error', 'label' => 'Unable to connect to database: <br />' . $db_connect_msg];
        }

        $checklist[] = ['type' => 'hr'];
        $checklist[] = ['type' => 'header', 'label' => 'Directories and Files that need to be Writable'];

        @touch( base_path('storage/logs/laravel.log') ); // Create the log file if it doesn't exist.

        $dir = $checklist_raw['writable'];

        foreach($dir as $d) {
           $checklist[] = ['type' => 'item', 'label' => 'Is Writable: ' . $d, 'success' => is_writable(base_path($d))];
        }

        $test = FALSE;

        if($test) {
            $checklist[] = ['type' => 'hr'];
            $checklist[] = ['type' => 'header', 'label' => 'PHP Extensions'];
            $checklist[] = ['type' => 'item', 'label' => 'Good thingy ', 'success' => TRUE];
            $checklist[] = ['type' => 'item', 'label' => 'OK thingy ', 'success' => NULL];
            $checklist[] = ['type' => 'item', 'label' => 'Bad thingy ', 'success' => FALSE];

            $rs = rand(1,3);
            $rsb = ($rs == 1);
            $rsb = ($rs == 3) ? NULL : $rsb;
            $checklist[] = ['type' => 'item', 'label' => 'Randomly thingy ', 'success' => $rsb];
        }

        $success = TRUE;

        foreach($checklist as $row) {
            if($row['type'] == 'item' && $row['success'] === FALSE) {
                $success = FALSE;
                break;
            }
        }

        return array($checklist, $success);
    }

    static function getServerUrl() 
    {
        if(array_key_exists('HTTP_HOST', $_SERVER)) {
            $current_domain = $_SERVER['HTTP_HOST'];
        }
        elseif(array_key_exists('SERVER_NAME', $_SERVER)) {
            $current_domain = $_SERVER['SERVER_NAME'];
        }
        else {
            $current_domain = 'example.com';
        }

        $http   = (array_key_exists('HTTPS', $_SERVER) && !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
        $server = $http . $current_domain;
        return $server;
    }

    static function getImportableDir() 
    {
        return [
            'Models\Books',
            'Models\Verses',
            'Models\Misc',
            'Traits',
            'Renders\Extras',
        ];
    }

    static function uninstall(Request $request) 
    {
        if(!defined('STDIN')) {
            define('STDIN', fopen('php://stdin', 'r'));
        }

        $InstalledBibles = Bible::where('installed', 1)->get();
        $success = TRUE;

        foreach($InstalledBibles as $B) {
            $B->uninstall();
            $success = ($B->hasErrors()) ? FALSE : $success;
        }

        // Remove all language-specific tables
        $languages = LanguageAttr::groupBy('code')->pluck('code');

        foreach($languages as $l) {
            $Lang = Language::findByCode($l);
            $Lang && $Lang->denitLanguage();
        }

        $exit_code = Artisan::call('migrate:reset', array('--force' => TRUE)); // Roll back ALL DB migrations

        if(\Schema::hasTable('migrations')) {
            \Schema::drop('migrations');
        }

        return $success;
    }
}
