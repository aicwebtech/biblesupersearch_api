<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\User;

/**
 * UserTableSeeder
 */
class UserTableSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (php_sapi_name() != "cli") {
            return; // this only runs in CLI mode
        }

        try {
            // Create an admin user
            User::create([
                'name'         => env('ADMIN_NAME', 'Admin User'),
                'email'        => env('ADMIN_EMAIL', 'test@example.com'),
                'username'     => env('ADMIN_USERNAME', 'admin'),
                'password'     => bcrypt( env('ADMIN_PASSWORD', 'admin') ),
                'access_level' => 100,
            ]);
        }
        catch(Exception $e) {

        }
    }
}
