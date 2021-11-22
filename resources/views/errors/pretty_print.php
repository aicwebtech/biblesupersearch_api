<div style='width: 600px; margin-left:auto; margin-right: auto; text-align: center'>
    <h1>Errors Have Occurred</h1>

    <ul style='text-align: left;'>
    <?php foreach($response->errors as $err): ?>
        <li><?php echo $err; ?></li>
    <?php endforeach; ?>
    </ul>

    <h2>Please go back and try again</h2>

    <?php if(array_key_exists('HTTP_REFERER', $_SERVER)): ?>
        <form action="<?php echo $_SERVER['HTTP_REFERER'] ?>" method='get' >
            <input type='submit' onclick='history.back(); return false;' value='Back'>
        </form>
    <?php else: ?>
        Please use your browser's Back button
    <?php endif; ?>
</div>