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
        try {
            // Create an admin user
            User::create([
                'name'     => env('ADMIN_NAME', 'Admin User'),
                'username' => env('ADMIN_USERNAME', 'admin'),
                'password' => bcrypt( env('ADMIN_PASSWORD', 'admin') ),
            ]);
        }
        catch(Exception $e) {

        }
    }
}
