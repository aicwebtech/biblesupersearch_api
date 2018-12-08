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
//        return TRUE;

        if(config('app.installed')) {
            return TRUE;
        }

        return FALSE;
    }

    static function install(Request $request) {
        // Generate application key
        Artisan::call('key:generate');

        // Set up database
        $exit_code = Artisan::call('migrate', array('--seed' => TRUE, '--force' => TRUE));

        // Install default Bible (usally KJV)
        $Bible = Bible::findByModule(config('bss.defaults.bible'));
        $Bible->install(FALSE, TRUE);

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

        return TRUE;
    }

    static function checkSettings() {
        // Read in composer settings
        $composer_txt = file_get_contents(base_path() . '/composer.json');
        $composer     = json_decode($composer_txt);

        $php_version = substr($composer->require->php, 2);
        $php_success = (version_compare(phpversion(), $php_version, '>=') == -1) ? TRUE : FALSE;
        $conname = config('database.default');
        $db_info = config('database.connections.' . $conname);
        $sqlite_required = ($db_info['driver'] == 'sqlite') ? TRUE : FALSE;

        $checklist = [];

        $checklist[] = ['type' => 'header', 'label' => 'Software'];
        $env = (is_file(base_path('.env')) && is_writable(base_path('.env'))) ? TRUE : FALSE;
        $checklist[] = ['type' => 'item', 'label' => '.env config file exists and is writable', 'success' => $env];
        $checklist[] = ['type' => 'item', 'label' => 'PHP Version >= ' . $php_version . ' (' . PHP_VERSION . ')', 'success' => $php_success];

        $extensions = ['OpenSSL', 'PDO', 'Mbstring', 'Tokenizer', 'XML', 'Zip'];
        $rec_extensions = [];

        if($sqlite_required) {
            $extensions[] = 'SQLite3';
        }
        else {
            $rec_extensions[] = 'SQLite3';
        }

        foreach($extensions as $ext) {
            $checklist[] = ['type' => 'item', 'label' => 'PHP Extension: ' . $ext, 'success' => extension_loaded($ext)];
        }        

        foreach($rec_extensions as $ext) {
            $checklist[] = ['type' => 'item', 'label' => 'PHP Extension: ' . $ext, 'success' => extension_loaded($ext) ?: NULL];
        }

        $checklist[] = ['type' => 'hr'];
        $checklist[] = ['type' => 'header', 'label' => 'Database'];


        if(empty($db_info)) {
            $checklist[] = ['type' => 'item', 'label' => 'Unknown DB_CONNECTION: ' . $conname, 'success' => FALSE];
        }

        $pdo_driver = 'PDO_' . strtoupper($db_info['driver']);

        $db_type_map = [
            'mysql'  => 'MySQL',
            'sqlite' => 'SQLite',
            // 'sqlsrv' => 'Microsoft SQL Server / SQL Azure',
            'pgsql'  => 'PostgreSQL'
        ];

        if(isset($db_type_map[$db_info['driver']])) {
            $checklist[] = ['type' => 'item', 'label' => 'Selected Database Type: ' . $db_type_map[$db_info['driver']], 'success' => TRUE];
        }
        else {
            $checklist[] = ['type' => 'item', 'label' => 'Unsupported Database Type: ' . $db_info['driver'], 'success' => FALSE];
        }

        $file = ($db_info['driver'] == 'sqlite') ? TRUE : FALSE;


        $checklist[] = ['type' => 'item', 'label' => 'Database PDO Driver: ' . $pdo_driver, 'success' => extension_loaded($pdo_driver)];

        if(!$file) {
            $checklist[] = ['type' => 'item', 'label' => 'DB_HOST ('. $db_info['host'] . ')', 'success' => (!empty($db_info['host']) || $file)];
            $checklist[] = ['type' => 'item', 'label' => 'DB_DATABASE ('. $db_info['database'] . ')', 'success' => (!empty($db_info['database']) || $file)];
            $checklist[] = ['type' => 'item', 'label' => 'DB_USERNAME ('. $db_info['username'] . ')', 'success' => (!empty($db_info['username']) || $file)];
            $checklist[] = ['type' => 'item', 'label' => 'DB_PASSWORD ('. $db_info['password'] . ')', 'success' => (!empty($db_info['password']) || $file)];
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

        // attempt to connect to db
        $able_to_connect = TRUE;

        try {
            \DB::connection()->getPdo();

            if($db_info['driver'] == 'sqlite') {
                $rows = \DB::select('SELECT * FROM sqlite_master');

                print_r($rows);
            }
        }
        catch (\Exception $e) {
            $able_to_connect = FALSE;
        }

        $checklist[] = ['type' => 'item', 'label' => 'Able to Connect', 'success' => $able_to_connect];

        $checklist[] = ['type' => 'hr'];
        $checklist[] = ['type' => 'header', 'label' => 'Directories that need to be Writable'];

        $dir = ['storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'];

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
            $rsb = ($rs == 1) ? TRUE : FALSE;
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
}
