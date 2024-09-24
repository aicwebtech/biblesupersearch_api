@php
    // this is currently the global build of vue.  
    // We may want the ES modules build as that's what I'm used to?? I think??
    $javascripts = [
        '/js/bin/vue/vue.3.5.8.js'
    ];

    $stylesheets = [
       // '/js/bin/jqGrid-v4.6.0/css/ui.jqgrid.css',
    ];
@endphp

@extends('layouts.admin')

@section('content')
    <div class='container'>
        <div id="app"></div>
    </div>

    <script type='module'>
        const { createApp } = Vue
        import App from '/js/admin/languages/App.vue.js';
        createApp(App).mount('#app')
    </script>
@endsection