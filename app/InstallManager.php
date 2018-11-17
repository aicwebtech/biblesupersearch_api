<?php

namespace App;

/**
 * Description of InstallManager
 *
 * @author Computer
 */
class InstallManager {
    //put your code here

    static function isInstalled() {
        if(config('app.installed')) {
            return TRUE;
        }

        return FALSE;
    }

    static function checkSettings() {
        // Read in composer settings
        $composer_txt = file_get_contents(base_path() . '/composer.json');
        $composer     = json_decode($composer_txt);

        $php_version = substr($composer->require->php, 2);
        $php_success = (version_compare(phpversion(), $php_version, '>=') == -1) ? TRUE : FALSE;

        $checklist = [];

        $checklist[] = ['type' => 'header', 'label' => 'Software'];

        $checklist[] = ['type' => 'item', 'label' => '.env config file', 'success' => is_file(base_path('.env'))];
        $checklist[] = ['type' => 'item', 'label' => 'PHP Version >= ' . $php_version, 'success' => $php_success];


        $extensions = ['OpenSSL', 'PDO', 'Mbstring', 'Tokenizer', 'XML'];

        foreach($extensions as $ext) {
            $checklist[] = ['type' => 'item', 'label' => 'PHP Extension: ' . $ext, 'success' => extension_loaded($ext)];
        }

        $checklist[] = ['type' => 'hr'];
        $checklist[] = ['type' => 'header', 'label' => 'Database'];

        $conname = config('database.default');
        $db_info = config('database.connections.' . $conname);

        if(empty($db_info)) {
            $checklist[] = ['type' => 'item', 'label' => 'Unknown DB_CONNECTION: ' . $conname, 'success' => FALSE];
        }

        $pdo_driver = 'PDO_' . strtoupper($db_info['driver']);

        $db_type_map = [
            'mysql'  => 'MySQL',
            'sqlite' => 'SQLite',
            'sqlsrv' => 'Microsoft SQL Server / SQL Azure',
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
            $checklist[] = ['type' => 'item', 'label' => 'DB file is writable: ' . $db_file, 'success' => $db_file_writable];
        }

        $checklist[] = ['type' => 'item', 'label' => 'DB_PREFIX ('. $db_info['prefix'] . ')', 'success' => (!empty($db_info['prefix'])) ? TRUE : NULL];

        // attempt to connect to db
        $able_to_connect = TRUE;

        try {
            \DB::connection()->getPdo();
        }
        catch (\Exception $e) {
            $able_to_connect = FALSE;
            //die("Could not connect to the database.  Please check your configuration. error:" . $e );
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
