<?php
    $title = config('app.name', 'Bible SuperSearch API') . ' Installed Successfully';
?>

@extends('layouts.install')

@section('content')

<script>
    var timeout = 10;
    var x = null;

    $(function() {
        $('#countdown').html('(' + timeout + ')');

        x = setInterval(function() {
            timeout -= 1;

            if(timeout == 0) {
                $('#form').submit();
            }

            $('#countdown').html('(' + timeout + ')');
        }, 1000);
    });

</script>

Continue to log in page <br /><br />

<form id='form' class="form-horizontal" method="GET" action="{{ route('login') }}">
    <button type="submit" class="button">Log In <span id='countdown'></span></button>
</form>
@endsection
