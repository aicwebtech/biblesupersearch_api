@php
    $javascripts = [
        '/js/bin/jqGrid-v4.6.0/js/jquery.jqGrid.js',
        '/js/bin/jqGrid-v4.6.0/js/i18n/grid.locale-en.js',
        '/js/bin/enyo/2.5.1.1/enyo.js',
        '/js/admin/bibles/editor/package.js'
    ];

    $stylesheets = [
       '/js/bin/jqGrid-v4.6.0/css/ui.jqgrid.css',
    ];
@endphp

@extends('layouts.admin')

@section('content')
    <div class='container'>
        <script>
            var bootstrap = @php echo $bootstrap @endphp;
        </script>
        
        <div class='content'>
            <?php echo csrf_field() ?>
            <div id='enyo_container'></div>
        </div>
    </div>
@endsection