@php
    // this is currently the global build of vue.  
    // We may want the ES modules build as that's what I'm used to?? I think??
    $javascripts = [
        '/js/bin/vue/vue.3.5.8.js',
        '/js/bin/vuetify/3.7.2/vuetify.min.js',
    ];

    $stylesheets = [
       // '/js/bin/jqGrid-v4.6.0/css/ui.jqgrid.css',
       '/js/bin/vuetify/3.7.2/vuetify.min.css',
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