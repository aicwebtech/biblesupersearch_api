@php
    // this is currently the global build of vue.  
    // We may want the ES modules build as that's what I'm used to?? I think??
    $javascripts = [
        '/js/bin/axios/axios.min.js',
        '/js/bin/vue/3.5.8/vue.global.min.js',
        '/js/bin/vuetify/3.7.2/vuetify.min.js',
    ];

    $stylesheets = [
       // '/js/bin/jqGrid-v4.6.0/css/ui.jqgrid.css',
       '/js/bin/vuetify/3.7.2/vuetify.min.css',
       'https://cdn.jsdelivr.net/npm/@mdi/font@5.x/css/materialdesignicons.min.css', // Works but remote
       //'/css/bin/mdi/5.x/materialdesignicons.min.css', // File loads, icons don't appear
    ];
@endphp

@extends('layouts.admin')

@section('content')
    <div class='container'>
        <div id="app"></div>
    </div>

    <script type='module'>
        const { createApp } = Vue
        const { createVuetify } = Vuetify

        const vuetify = createVuetify()
        import App from '/js/admin/languages/App.vue.js';
        
        const app = createApp(App)
        app.use(vuetify)
        app.mount('#app')
    </script>
@endsection