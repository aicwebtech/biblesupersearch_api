<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

// This isn't autoloading??
require_once(dirname(__FILE__) . '/UserTableSeeder.php');

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $this->call('UserTableSeeder');

        Model::reguard();
    }
}
