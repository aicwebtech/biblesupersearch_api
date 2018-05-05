@extends('layouts.admin')

@section('content')
    <div class='container'>
        <div class='content'>
            <?php echo csrf_field() ?>
            TO DO - ACTUALLY BUILD THIS PAGE!<br /><br />
            <a href='<?php echo route('logout') ?>'>Log Out</a>
        </div>
    </div>
@endsection
