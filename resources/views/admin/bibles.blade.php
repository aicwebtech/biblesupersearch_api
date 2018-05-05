@php
    $javascripts = [
        //'/js/bin/jqGrid-v4.6.0/jquery.js',
        '/js/bin/jqGrid-v4.6.0/js/jquery.jqGrid.js',
        '/js/bin/jqGrid-v4.6.0/js/i18n/grid.locale-en.js',
        '/js/bin/enyo/2.5.1.1/enyo.js',
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
            <table id='grid'></table>
            <div id='grid_footer'></div>

            <div id='enyo_container'></div>
        </div>
    </div>
@endsection