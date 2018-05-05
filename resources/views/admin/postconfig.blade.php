@php
    $javascripts = ['/js/bin/ckeditor/ckeditor.js'];
@endphp

@extends('layouts.admin')

@section('content')
    <div class='container'>
        <div class='content'>
            <h2 class='title'>{{$Post->title}}</h2>
            <form method='POST'>
                <?php echo csrf_field() ?>
                <h5>Please specify your {{$Post->title}} here.  It will appear on the documentation page.</h5>
                <textarea name='content' id='content'>{{$Post->content}}</textarea>
                <script>
                    CKEDITOR.replace('content', {
                        height: 400
                    });
                </script>

                <br /><br />
                <input type='submit' value='Save' class='button' />
            </form>
        </div>
    </div>
@endsection

