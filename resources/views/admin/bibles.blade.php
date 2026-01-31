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
       '/css/bin/mdi/5.x/materialdesignicons.min.css', 
       '/css/vue/vue.css',
       '/js/admin/app/assets/style.css',
       '/js/admin/bibles/assets/style.css',
       '/js/bin/custom_vue/composables/grid/grid.css',
    ];

    $u = url('');
@endphp

@extends('layouts.admin')

@section('content')
    <div class='container'>
        <div id="app"></div>
    </div>

    <script>
        var bootstrap = @php echo $bootstrap @endphp;
    </script>

    <script type='module' src='{{$u}}/js/admin/bibles/Bootstrap.vue.js'></script>
@endsection