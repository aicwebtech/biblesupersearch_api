<?php
    $context = 'api.version';
    $url = '/version';
    include( dirname(__FILE__) . '/generic.php');

    renderParameterHeader();
    renderCommonParameters(['callback']);
    renderParameterFooter();
?>

<div>
    <?php echo trans('api.data_structure') . ' ' . trans('api.example'); ?>:
</div>

<pre><code>{
    "errors": [],
    "error_level": 0,
    "results": {
        "name": "<?php echo config('app.name') ?>",
        "version": "<?php echo config('app.version') ?>",
        "environment": "<?php echo config('app.env') ?>",
    }
}
</code></pre>