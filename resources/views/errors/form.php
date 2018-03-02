<?php if(count($errors) > 0): ?>
<!-- Form Error List -->
<div class="alert alert-danger">
    <strong>Whoops! Something went wrong!</strong>
    <br />

    <ul>
        <?php foreach($errors->all() as $error): ?>
            <li><?php echo($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
