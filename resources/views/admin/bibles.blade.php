@php
    $javascripts = [
        //'/js/bin/jqGrid-v4.6.0/jquery.js',
        '/js/bin/jqGrid-v4.6.0/js/jquery.jqGrid.js',
        '/js/admin/bibles/package.js'
    ];

    $stylesheets = [
       '/js/bin/jqGrid-v4.6.0/css/ui.jqgrid.css'
    ];
@endphp

@extends('layouts.admin')

@section('content')
    <div class='container'>
        <div class='content'>
            <?php echo csrf_field() ?>
            Bibles go here<br /><br />
            <a href='<?php echo route('logout') ?>'>Log Out</a>

            <table id='grid'></table>
            <div id='grid_footer'></div>
        </div>
    </div>
@endsection