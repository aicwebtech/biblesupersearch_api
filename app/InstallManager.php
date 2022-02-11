<?php

namespace App;
use Illuminate\Http\Request;
use App\Models\Bible;
use Artisan;
use App\ConfigManager;

/**
 * Description of InstallManager
 *
 * @author Computer
 */
class InstallManager {
    static function isInstalled() {
        if(config('app.installed')) {
            return TRUE;
        }

        return FALSE;
    }

    static function install(Request $request) {
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

    static function checkSettings() {
        // Read in composer settings
        $composer_txt = file_get_contents(base_path() . '/composer.json');
        $composer     = json_decode($composer_txt);

        $php_version = substr($composer->require->php, 2);
        $php_success = (version_compare(phpversion(), $php_version, '>=') == -1);
        $conname = config('database.default');
        $db_info = config('database.connections.' . $conname);
        $sqlite_required = ($db_info['driver'] == 'sqlite');

        $checklist = [];
        $installed_php_parts = explode('.', PHP_VERSION);
        $installed_php = $installed_php_parts[0] . '.' . $installed_php_parts[1] . '.' . intval($installed_php_parts[2]);

        // TODO - MAKE SURE SENDMAIL IS INSTALLED!

        $checklist[] = ['type' => 'header', 'label' => 'Software'];
        $env = (is_file(base_path('.env')) && is_writable(base_path('.env')));
        $checklist[] = ['type' => 'item', 'label' => '.env config file exists and is writable', 'success' => $env];
        $checklist[] = ['type' => 'item', 'label' => 'PHP Version >= ' . $php_version . ' (' . $installed_php . ')', 'success' => $php_success];

        $extensions = ['OpenSSL', 'PDO', 'Mbstring', 'Tokenizer', 'XML', 'Zip', 'Ctype', 'JSON', 'BCMath', 'gd', 'Fileinfo'];
        $rec_extensions = [];

        if($sqlite_required) {
            $extensions[] = 'SQLite3';
        }
        else {
            $rec_extensions[] = 'SQLite3';
        }

        sort($extensions);
        sort($rec_extensions);

        foreach($extensions as $ext) {
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

        $db_type_map = [
            'mysql'  => 'MySQL',
            // 'sqlite' => 'SQLite',
            // 'sqlsrv' => 'Microsoft SQL Server / SQL Azure',
            // 'pgsql'  => 'PostgreSQL'
        ];

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

        try {
            \DB::connection()->getPdo();

            if($db_info['driver'] == 'sqlite') {
                $rows = \DB::select('SELECT * FROM sqlite_master');
            }
        }
        catch (\Exception $e) {
            $able_to_connect = FALSE;
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
            $checklist[] = ['type' => 'header', 'label' => 'Unable to connect to database; Please check your database credentials'];
        }

        $checklist[] = ['type' => 'hr'];
        $checklist[] = ['type' => 'header', 'label' => 'Directories and Files that need to be Writable'];

        @touch( base_path('storage/logs/laravel.log') ); // Create the log file if it doesn't exist.

        $dir = [
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
        ];

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

    static function getServerUrl() {
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

    static function getImportableDir() {
        return [
            'Models\Books',
            'Models\Verses',
            'Models\Misc',
            'Traits',
            'Renders\Extras',
        ];
    }

    static function uninstall(Request $request) {
        if(!defined('STDIN')) {
            define('STDIN', fopen('php://stdin', 'r'));
        }

        $InstalledBibles = Bible::where('installed', 1)->get();
        $success = TRUE;

        foreach($InstalledBibles as $B) {
            $B->uninstall();
            $success = ($B->hasErrors()) ? FALSE : $success;
        }

        $exit_code = Artisan::call('migrate:reset', array('--force' => TRUE)); // Roll back ALL DB migrations

        if(\Schema::hasTable('migrations')) {
            \Schema::drop('migrations');
        }

        return $success;
    }
}
