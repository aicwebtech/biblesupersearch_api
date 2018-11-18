<?php
    $title = config('app.name', 'Bible SuperSearch API') . ' Config';
?>

@extends('layouts.install')

@section('content')

<script>
    $(function() {
        $('#config_form').submit(function() {
            $('#config-submit').prop('disabled', true);
            $('#config-submit').html('Installing, please wait ...');
        });
    });
</script>

Please provide details for creating the admin user for {{config('app.name')}} <br /><br />

@if ($errors->any())
    <div class="error">
        @foreach ($errors->all() as $error)
            <p class='message'>{{ $error }}</p>
        @endforeach
    </div>
@endif

<form id='config_form' class="form-horizontal" method="POST" action="{{ route('admin.install.config.process') }}">
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

@endsection



