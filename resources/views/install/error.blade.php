<?php
    $title = config('app.name', 'Bible SuperSearch API') . ' Installation Problem';
?>

@extends('layouts.install')

@section('content')

<p class='bad'>{{ $message }}</p>

@if($retry)

<form class="form-horizontal" method="GET" action="{{ route('admin.install') }}">
    <button type="submit" class="button">Back to the installer</button>
</form>

@endif

@endsection
