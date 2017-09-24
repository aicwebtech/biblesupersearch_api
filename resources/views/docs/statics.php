<?php
    $context = 'api.statics';
    $url = '/statics';
    renderActionHeader($context, $url);
    renderParameterHeader();
    renderCommonParameters(['language', 'callback']);
    renderParameterFooter();
?>

<div>
    <?php echo trans('api.data_structure') . ' ' . trans('api.example'); ?>:
</div>

<pre><code>{
    "errors": [],
    "error_level": 0,
    "results": {
        "bibles": { ... }, // <?php echo trans('api.see') . ' ' . trans('api.bibles.name') . ' ' . trans('api.action'). PHP_EOL ?>
        "books": [ ... ],  // <?php echo trans('api.see') . ' ' . trans('api.books.name')  . ' ' . trans('api.action'). PHP_EOL ?>
        "search_types": [ ... ],
        "shortcuts": [ ... ],
        "name": "<?php echo config('app.name') ?>",
        "version": "<?php echo config('app.version') ?>",
        "environment": "<?php echo config('app.env') ?>",
    }
}
</code></pre>