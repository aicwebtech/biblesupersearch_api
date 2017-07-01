<?php
    $context = 'api.books';
    $url = '/books';
    //include( dirname(__FILE__) . '/generic.php');

    renderActionHeader($context, $url);
    renderParameterHeader();
    renderCommonParameters(['language', 'callback']);
    renderParameterFooter();
?>

<div>
    <?php echo trans('api.data_structure') . ' ' . trans('api.example'); ?>:
</div>

<pre><code>{
    "errors":[],
    "error_level":0,
    "results":[
        {"id":1,"name":"Genesis","shortname":"Gen"},
        {"id":2,"name":"Exodus","shortname":"Ex"},
        ....
        {"id":65,"name":"Jude","shortname":"Jude"},
        {"id":66,"name":"Revelation","shortname":"Rev"}
    ]
}
</code></pre>