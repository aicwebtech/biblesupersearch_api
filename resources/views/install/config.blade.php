<?php
    $title = config('app.name', 'Bible SuperSearch API') . ' Config';
?>

@extends('layouts.install')

@section('content')

<script>
    var sec = 0;
    var timeoutSec = 1;

    $(function() {
        $('#config_form').submit(function() {
            $('#config-submit').prop('disabled', true);

            x = setInterval(function() {
                if(timeoutSec > 0) {
                    timeoutSec -= 1;
                    return;
                }

                if(timeoutSec == 0) {
                    $('#config-submit').html('Installing, please wait ...');
                    $('#install-timer-container').show();
                    $('#config_form').hide();
                    $('#title').hide();
                    $('#title-alt').show();
                    $('.error').hide();
                    timeoutSec = -1;
                }

                var m = Math.floor(sec / 60);
                var s = sec - m * 60;
                var mDisp = (m < 10) ? '0' + m : m;
                var sDisp = (s < 10) ? '0' + s : s;

                $('#counter').html(mDisp + ':' + sDisp);
                sec ++;

                if(sec > 120) {
                    $('#install-timer-container').css('background-color', '#ff704d');
                }
            }, 1000);
        });
    });
</script>


@if ($errors->any())
    <div class="error">
        @foreach ($errors->all() as $error)
            <p class='message'>{{ $error }}</p>
        @endforeach
    </div>
@endif

<form id='config_form' class="form-horizontal" method="POST" action="{{ route('admin.install.config.process') }}">
    Please provide details for creating the admin user for {{config('app.name')}} <br /><br />
    <h3>Name</h3>
    <input id="name" type="text" class="form-control" name="name" required value='@isset($input['name']){{$input['name']}}@endisset'><br />
    <h3>Username</h3>
    <input id="username" type="text" class="form-control" name="username" required value='@isset($input['username']){{$input['username']}}@endisset'><br />
    <h3>Email Address</h3>
    <input id="email" type="email" class="form-control" name="email" required value='@isset($input['email']){{$input['email']}}@endisset'><br />
    <h3>Password</h3>
    <input id="password" type="password" class="form-control" name="password" required><br />
    <h3>Confirm Password</h3>
    <input id="password2" type="password" class="form-control" name="password2" required same:password><br />

    <br /><br />
   {{ csrf_field() }}
   <button id='config-submit' type="submit" class="button">Install</button>

</form>

<h2 id='title-alt' style='display:none'>Installing {{config('app.name')}} ...</h2>

<div id='install-timer-container' style='display:none'>
    Installation might take a couple minutes.  Please wait. <br /><br />
    Do not use your browser's back button. <br /><br />

    <span id='counter'></span>
</div>

@endsection



