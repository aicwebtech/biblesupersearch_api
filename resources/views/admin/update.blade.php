@extends('layouts.admin')

@section('content')
<div style='width: 400px' class='center_div'>
    <h2>Checking for Updates</h3>
    <table>
        <tr><td>Local Version:</td><td>{{$local}}</td></tr>
        <tr><td>Current Version:</td><td>@if($upstream === NULL)(unknown)@else{{$upstream}}@endif</td></tr>
    </table>
    <br /><br />
        @if($upstream === NULL)Cannot check for updates, please try again later.<br /><br />@endif

        @if($update)
            Please download and install the latest updates!<br /><br />

            <a href='https://www.biblesupersearch.com/downloads/' target='_NEW'>Bible SuperSearch Downloads</a>
        @else
            You are up to date!
        @endif
</div>


@endsection
