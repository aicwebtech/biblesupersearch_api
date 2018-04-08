@php
    $buttons = [
        ['label' => 'Dashboard', 'route' => 'admin.main'],
        ['label' => 'Bibles', 'route' => 'admin.bibles.index'],
        ['label' => 'Options', 'route' => 'admin.configs'],
        ['label' => 'Terms of Service', 'route' => 'admin.tos'],
        ['label' => 'Privacy Policy', 'route' => 'admin.privacy'],
        ['label' => 'Test', 'route' => 'admin.test'],
        ['label' => 'Log Out', 'route' => 'logout'],
    ];

if(!isset($javascripts)) {
    $javascripts = array();
}
else if(!is_array($javascripts)) {
    $javascripts = array($javascripts);
}

if(isset($include_enyo) && $include_enyo) {
    $javascripts[] = '/js/bin/enyo/2.5.1.1/enyo.js';
}

@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta contentType="text/html; charset=UTF-8"/>
        <link rel="stylesheet" href="/css/admin/admin.css" >
        <link rel="stylesheet" href="/js/bin/jquery-ui/jquery-ui.css">
        <link rel="stylesheet" href="/js/bin/jquery-ui/jquery-ui.theme.css">
        @if( isset($stylesheets) && is_array($stylesheets) )
        @foreach ($stylesheets as $css)
        <link rel="stylesheet" href="{{ asset($css) }}">
        @endforeach
        @endif

        <script src='/js/bin/jquery/jquery-3.1.1.min.js'></script>
        <!--<script src="https://code.jquery.com/jquery-migrate-3.0.1.js"></script>-->
        <script src='/js/bin/jquery-ui/jquery-ui.js'></script>
        <script src='/js/admin/admin.js'></script>
        @if( isset($javascripts) && is_array($javascripts) )
        @foreach ($javascripts as $js)
        <script src='{{ asset($js) }}'></script>
        @endforeach
        @endif

        <script>
            var laravelCsrfToken = '{{ csrf_token() }}';
        </script>
        <title>{{ config('app.name', 'Laravel') }} - Manager</title>
    </head>
    <body>
        <div id='header'>
            <h1>Bible SuperSearch API Installation Manager</h1>
            <div id='top_menu'>
                @foreach ($buttons as $button)
                    <a href='{{ route($button['route']) }}' class='menu_item @if(Route::currentRouteName() == $button['route'])active @endif'>{{ $button['label'] }}</a>
                @endforeach
            </div>
        </div>

        <div id="app">
            @yield('content')
        </div>

        <div id='footer'>
            Bible SuperSearch API Version {{ config('app.version') }}.  Copyright &copy; 2006 - {{ date('Y') }} &nbsp;
            <a href='https://biblesupersearch.com'target="_NEW">BibleSuperSearch.com</a> &nbsp;
            This open source software is licensed under the terms of the
            <a href='https://www.gnu.org/licenses/gpl-3.0.en.html' target="_NEW">GNU General Public License, Version 3</a>.
        </div>
    </body>
</html>