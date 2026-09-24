<?php
    $context = 'api.access';
    $url = '/access';

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
        "allowed": true,
        "limit": <?php echo (int) config('bss.daily_access_limit') ?>,
        "limit_reached": false,
        "hits": 17
    }
}
</code></pre>
