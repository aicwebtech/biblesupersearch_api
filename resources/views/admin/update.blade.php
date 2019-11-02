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
            @if($php_update)
            There is an update available.  <br /><br />

            However, you need to update your website's PHP before you can download and install it.<br /><br /><br />

            <table>
                <!-- <tr><td>Local PHP Version:</td><td>{{$php_local}}</td></tr> -->
                <tr><td>Minimum PHP Version Needed:</td><td>{{$php_min}}</td></tr>
            </table>
            @else
                Please download and install the latest update!<br /><br />

                <a href='https://www.biblesupersearch.com/downloads/' target='_NEW'>Bible SuperSearch Downloads</a>
            @endif

        @else
            You are up to date!
        @endif
</div>


@endsection
