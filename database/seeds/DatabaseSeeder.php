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
        $this->call('Bibles');
        $this->call('IndexTableSeeder');
        $this->call('BookListSeeder');
        $this->call('ShortcutsSeeder');
        $this->call('StrongsDefinitionsSeeder');

        Model::reguard();
    }
}
