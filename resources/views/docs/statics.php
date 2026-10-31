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
        "volumes": { ... }, // <?php echo trans('api.see') . ' ' . trans('api.volumes.name') . ' ' . trans('api.action'). PHP_EOL ?>
        "books": [ ... ],  // <?php echo trans('api.see') . ' ' . trans('api.books.name')  . ' ' . trans('api.action'). PHP_EOL ?>
        "shortcuts": [ ... ],
        // <?php echo trans('api.statics.features_desc') , PHP_EOL ?>
        "features": {
            "audio": {"enabled": true},
            "download": {
                "enabled": true,
                "formats": [ ... ],
                "limit": 10     // <?php echo trans('api.statics.download_limit_desc') , PHP_EOL ?>
            },
            "cross_references": {"enabled": true},
            "strongs": {
                "enabled": true,                // <?php echo trans('api.statics.strongs_enabled_desc') , PHP_EOL ?>
                "modern": {"enabled": true},    // <?php echo trans('api.statics.strongs_modern_desc') , PHP_EOL ?>
                "legacy": {"enabled": true}     // <?php echo trans('api.statics.strongs_legacy_desc') , PHP_EOL ?>
            }
        },
        "search_types": [ ... ],
        "name": <?php echo e(json_encode(config('app.name'))) ?>,
        "version": <?php echo e(json_encode(config('app.version'))) ?>,
        "environment": <?php echo e(json_encode(config('app.env'))) ?>,
    }
}
</code></pre>

<div>
    <?php echo trans('api.statics.v3_note'); ?>
</div>
