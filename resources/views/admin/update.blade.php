@extends('layouts.admin')

@section('content')
<h1>Check for Updates</h1>
<table>
    <tr><td>Local Version:</td><td>{{$local}}</td></tr>
    <tr><td>Current Version:</td><td>@if($upstream === NULL)(unknown)@else{{$upstream}}@endif</td></tr>
</table>
<br /><br />
    @if($upstream === NULL)Cannot check for updates, please try again later.<br /><br />@endif

    @if($update)
        Please download and install the latest updates!
    @else
        You are up to date!
    @endif

@endsection
