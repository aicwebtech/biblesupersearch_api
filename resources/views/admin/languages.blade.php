@php
    // this is currently the global build of vue.  
    // We may want the ES modules build as that's what I'm used to?? I think??
    $javascripts = [
        '/js/bin/axios/axios.min.js',
        '/js/bin/vue/3.5.8/vue.global.min.js',
        '/js/bin/vuetify/3.7.2/vuetify.min.js',
    ];

    $stylesheets = [
       '/js/bin/vuetify/3.7.2/vuetify.min.css',
       //'https://cdn.jsdelivr.net/npm/@mdi/font@5.x/css/materialdesignicons.min.css', // Works but remote
       '/css/bin/mdi/5.x/materialdesignicons.min.css', // File loads, icons don't appear
       '/css/vue/vue.css',
       '/js/admin/app/assets/style.css',
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

        import theme1 from '/css/vue/theme1.js';

        const vuetify = createVuetify({
            theme: {
                defaultTheme: 'theme1', 
                themes: {
                    theme1
                }
            }
        });

        import App from '/js/admin/languages/App.vue.js';
        // import App from '@/App.vue';
        
        const app = createApp(App)
        app.use(vuetify)
        app.mount('#app')
    </script>
@endsection