@extends('layouts.login')

@section('content')
    <div class='container'>
        <div class='content'>
            <form action='/auth/login' method='POST'>
                <!--<input type='submit' value='Log In' class='button' /><br /><br />-->
                <?php echo csrf_field() ?>
                PASSWORD RESET SUCCESSFULLY!<br /><br />
                <a href='<?php echo route('login') ?>'>Login</a>
            </form>
        </div>
    </div>
@endsection

