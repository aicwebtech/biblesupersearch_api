<?php
    $context = 'api.version';
    $url = '/version';
    //include( dirname(__FILE__) . '/generic.php');

    renderActionHeader($context, $url);
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
        "name": <?php echo e(json_encode(config('app.name'))) ?>,
        "version": <?php echo e(json_encode(config('app.version'))) ?>,
        "environment": <?php echo e(json_encode(config('app.env'))) ?>,
    }
}
</code></pre>