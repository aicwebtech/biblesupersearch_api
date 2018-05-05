@extends('layouts.admin')

@section('content')
    <div class='container'>
        <div class='content'>
            <?php echo csrf_field() ?>
            Logged in Successfully!<br /><br />
            <a href='<?php echo route('logout') ?>'>Log Out</a>
        </div>
    </div>
@endsection