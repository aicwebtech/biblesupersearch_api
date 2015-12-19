<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\User;

class ExampleTest extends TestCase
{
    /**
     * A basic functional test example.
     *
     * @return void
     */
    public function testBasicExample()
    {
        $this->visit('/')
             ->see('Laravel 5');
    }

    public function testUser() {
        $users = DB::select('select * from bss_users');

        print_r($users);        

        $users = DB::table('users')->get();

        print_r($users);

        //$this->assertCount($users, 1);

        /*
        $data = array(
            ':email' => 'goofyball12@gmail.com',
            ':created' => date('Y-m-d H:i:s', strtotime('yesterday'))
        );

        DB::update('update bss_users set email=:email, created_at=:created',$data);
        */
    }
}
