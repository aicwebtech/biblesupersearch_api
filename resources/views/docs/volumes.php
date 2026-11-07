<?php
    $context = 'api.volumes';
    $url = '/volumes';

    $params = ['bible_order_by', 'language_float'];

    renderActionHeader($context, $url);
    renderParameterHeader();
    renderParameters($params, 'api.volumes');
    renderCommonParameters(['callback']);
    renderParameterFooter();
?>

<div>
    <?php echo trans('api.data_structure') . ' ' . trans('api.example'); ?>:
</div>

<pre><code>{
    "errors":[],
    "error_level":0,
    "results":{
        // <?php echo trans('api.volumes.grouped_by_type') , PHP_EOL ?>
        "strongs":{
            // <?php echo trans('api.volumes.indexed_by_module') , PHP_EOL ?>
            "en_orig":{
                "type":"strongs",
                "name":"Strong's Exhaustive Concordance",
                "shortname":"Strong's",
                "module":"en_orig",
                "year":"1890",
                "owner":null,
                "publisher":null,
                "description":null,
                "url":null,
                "lang":"English",
                "lang_short":"en",
                "lang_native":"English",
                "rtl":0,
                "copyright":0,
                "copyright_id":1,
                "copyright_statement":"This Strong's Dictionary is in the Public Domain.",
                "rank":1000,
                "research":0,
                "restrict":0,
                "citation_limit":0,
                "is_default":true   // <?php echo trans('api.volumes.is_default_desc') , PHP_EOL ?>
            }
        }
    }
}
</code></pre>
