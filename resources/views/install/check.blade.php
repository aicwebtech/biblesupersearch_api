<?php
    $title = config('app.name', 'Bible SuperSearch API') . ' Check';
    $rowcount = 0;
?>

@extends('layouts.install')

@section('content')
Let's check to see if you're ready to install {{ config('app.name') }} on your server. <br /><br />

<table style='width: 100%'>

<?php foreach($checklist as $row): ?>
    <?php $rowcount ++; ?>

    <?php 
        if($row['type'] == 'header'): ?>
            <tr><th colspan='2'><?php echo $row['label']; ?></th></tr>        
        <?php elseif($row['type'] == 'error'): ?>
            <tr><th colspan='2' class='bad'><?php echo $row['label']; ?></th></tr>
        <?php elseif($row['type'] == 'hr'): ?>
            <tr><td colspan='2'><hr /></td></tr>
        <?php else: ?>
            <tr <?php if($rowcount %2 == 0):?>class='zebra'<?php endif;?> >
                <td><?php echo $row['label']; ?></td>
                <?php if($row['success'] === NULL): ?>
                    <td class='ok'>Okay</td>
                <?php elseif($row['success'] == TRUE): ?>
                    <td class='good'>Good</td>
                <?php else: ?>
                    <td class='bad'>Bad</td>
                <?php endif; ?>
            </tr>
        <?php endif; ?>
<?php endforeach; ?>


</table>

<br /><br />

<?php if($success): ?>

You are ready to install {{ config('app.name') }}. <br /><br />

<form class="form-horizontal" method="POST" action="{{ route('admin.install.config') }}">
   {{ csrf_field() }}
   <button type="submit" class="button">Continue</button>
</form>

<?php else: ?>

Please fix the the items in red, and check again. <br /><br />

<form class="form-horizontal" method="POST" action="{{ route('admin.install.check') }}">
   {{ csrf_field() }}
   <button type="submit" class="button">Re-Check</button>
</form>

<?php endif; ?>

@endsection



