@php
    $buttons = [
        // ['label' => 'Dashboard', 'route' => 'admin.main', 'new_tab' => FALSE, 'hash' => ''],
        ['label' => 'Bibles', 'route' => 'admin.bibles.index', 'new_tab' => FALSE, 'hash' => ''],
        ['label' => 'Languages', 'route' => 'admin.languages', 'new_tab' => FALSE, 'hash' => ''],
        ['label' => 'Options', 'route' => 'admin.configs', 'new_tab' => FALSE, 'hash' => ''],
        ['label' => 'Terms of Service', 'route' => 'admin.tos', 'new_tab' => FALSE, 'hash' => ''],
        ['label' => 'Privacy Policy', 'route' => 'admin.privacy', 'new_tab' => FALSE, 'hash' => ''],
        // ['label' => 'Help', 'route' => 'admin.help', 'new_tab' => FALSE, 'hash' => ''],
        ['label' => 'API Documentation', 'route' => 'docs', 'new_tab' => TRUE, 'hash' => ''],
        'exports' => ['label' => 'Bible Exports / Downloads', 'route' => 'docs', 'new_tab' => TRUE, 'hash' => '#tab_downloads'],
        ['label' => 'Update', 'route' => 'admin.update', 'new_tab' => FALSE, 'hash' => ''],
        ['label' => 'Uninstall', 'route' => 'admin.uninstall', 'new_tab' => FALSE, 'hash' => ''],
        ['label' => 'Log Out', 'route' => 'logout', 'new_tab' => FALSE, 'hash' => ''],
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

if(isset($include_vue) && $include_vue) {
    $javascripts[] = '/js/bin/vue/vue.3.5.8.js';
}

if(!config('download.enable') || !config('download.tab_enable')) {
    unset($buttons['exports']);
}

if(!isset($hide_menus)) {
    $hide_menus = FALSE;
}

$u = url('');

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
        <link rel="stylesheet" href="{{ $u }}/css/common.css" >
        <link rel="stylesheet" href="{{ $u }}/css/admin/admin.css" >
        <link rel="stylesheet" href="{{ $u }}/js/bin/jquery-ui/jquery-ui.css">
        <link rel="stylesheet" href="{{ $u }}/js/bin/jquery-ui/jquery-ui.theme.css">
        @if( isset($stylesheets) && is_array($stylesheets) )
        @foreach ($stylesheets as $css)
        <link rel="stylesheet" href="{{ asset($css) }}">
        @endforeach
        @endif

        <script src='{{ $u }}/js/bin/jquery/jquery-3.1.1.min.js'></script>
        <!--<script src="https://code.jquery.com/jquery-migrate-3.0.1.js"></script>-->
        <script src='{{ $u }}/js/bin/jquery-ui/jquery-ui.js'></script>
        <script src='{{ $u }}/js/admin/admin.js'></script>
        @if( isset($javascripts) && is_array($javascripts) )
        @foreach ($javascripts as $js)
        <script src='{{ asset($js) }}'></script>
        @endforeach
        @endif

        <script>
            var laravelCsrfToken = '{{ csrf_token() }}';
        </script>
        <title>{{ config('app.name', 'Bible SuperSearch API') }} - Manager</title>
    </head>
    <body>
        
        <div id='vuetest'></div>

        <div id='header'>
            <h1>{{ config('app.name', 'Bible SuperSearch API') }} Manager</h1>
            @if(!$hide_menus)
                <div id='top_menu'>
                    @foreach ($buttons as $button)
                        <a href='{{ route($button['route']) }}{{$button['hash']}}'
                           class='menu_item @if(Route::currentRouteName() == $button['route'])active @endif'
                           @if($button['new_tab'])target='_NEW'@endif;
                           >
                           {{ $button['label'] }}
                        </a>
                    @endforeach
                </div>
                @if( !config('download.enable'))
                    <div class='error'>
                        Bible downloads is not enabled.  &nbsp;<a href='/admin/config#tab_download'>Please enable</a> it now.
                    </div>
                @endif
            @endif
        </div>

        <div id="app">
            @yield('content')
        </div>

        <div id='page_loading_dialog' style='display: none'>
            Updating database, please wait ...
        </div>

        <div id='footer'>
            Bible SuperSearch API Version {{ config('app.version') }}.  Copyright &copy; 2006 - {{ date('Y') }} &nbsp;
            <a href='https://biblesupersearch.com'target="_NEW">BibleSuperSearch.com</a> 
            <br /><br />
            This open source software is licensed under the terms of the
            <a href='https://www.gnu.org/licenses/gpl-3.0.en.html' target="_NEW">GNU General Public License, Version 3</a>.
            <br /><br />
            Built and maintiained by 
            <a class='footer-link' href='http://www.aicwebtech.com/' target='_NEW'>AIC Web Tech</a>: 
            <a class='footer-link' href='http://www.aicwebtech.com/' target='_NEW'>aicwebtech.com</a>
        </div>
    </body>
</html>
