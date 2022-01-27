@extends('layouts.admin', ['hide_menus' => TRUE])

@section('content')

<div style='width: 400px' class='center_div'>
    <h2>Uninstall Successful</h3>

    <p>
        Bible SuperSearch has been removed from your database.  <br /><br />

        You will need to manually remove the Bible SuperSearch files from your filesystem. <br /><br />

        <form action='/install'>
            <input type='submit' value='Re-install' class='button' />
        </form>
        
    </p>
</div>

@endsection
