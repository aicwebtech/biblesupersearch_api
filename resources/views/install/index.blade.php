<?php
    $title = config('app.name', 'Bible SuperSearch API') . ' Installer';
?>

@extends('layouts.install')

@section('content')
This will install the Bible SuperSearch API on your server. <br /><br />

<form class="form-horizontal" method="POST" action="{{ route('admin.install.check') }}">
   {{ csrf_field() }}
   <button type="submit" class="button">Continue</button>
</form>
@endsection


