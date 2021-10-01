@extends('layouts.admin')

@section('content')
<div style='width: 400px' class='center_div'>
    <h2>Uninstall Bible SuperSearch API?</h3>
    <form id='uninstall_form' method='POST'>
        @csrf

        This will completely remove Bible SuperSearch from your database.<br /><br />

        Do you wish to proceed?<br /><br />

        <input type='submit' name='confirm' class='button' value='Yes' id='confirm_yes'>&nbsp; &nbsp;
        <input type='submit' name='confirm' class='button' value='No' id='confirm_no'>
    </form>
</div>

<script type="text/javascript">
    $('.button').button();

    $('#confirm_yes').click(function(e) {
        if(!window.confirm('Are you sure?')) {
            e.preventDefault();
            return false;
        }
    });
</script>

@endsection
