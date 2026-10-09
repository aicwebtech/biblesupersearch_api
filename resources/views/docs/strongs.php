<?php
    $context = 'api.strongs';
    $url = '/strongs';

    $params = ['strongs', 'version', 'language', 'dictionary'];

    renderActionHeader($context, $url);
    renderParameterHeader();
    renderParameters($params, 'api.strongs');
    renderCommonParameters(['callback']);
    renderParameterFooter();
?>

<div>
    <?php echo trans('api.data_structure') . ' ' . trans('api.example'); ?>:
</div>

<h5><?php echo trans('api.strongs.results.legacy'); ?></h5>

<h5><?php echo trans('api.strongs.results.tvm_note'); ?></h5>

<pre><code>{
    "errors":[],
    "error_level":0,
    "results":[
        // <?php echo trans('api.strongs.results.def') , PHP_EOL ?>
        {
            "id":11398,
            "number":"G2545",
            "root_word":"&#954;&#945;&#953;&#769;&#969;",
            "transliteration":"kaio&#772;",
            "pronunciation":"kah'-yo",
            "tvm":"",
            "entry":"Apparently a primary verb; to &lt;i>set on fire&lt;\/i&gt;, that is, &lt;i>kindle&lt;\/i&gt;  or (by implication) &lt;i&gt;consume:&lt;\/i&gt;  - burn, light."
        },
        // <?php echo trans('api.strongs.results.tvm') , PHP_EOL ?>
        {
            "id":14471,
            "number":"G5719",
            "root_word":null,
            "transliteration":null,
            "pronunciation":null,
            "tvm":"&lt;b&gt;Tense:&lt;\/b&gt; Present, See G5774 &lt;br&gt;&lt;b&gt;Voice:&lt;\/b&gt; Active, See G5784 &lt;br&gt;&lt;b&gt;Mood:&lt;\/b&gt; Indicative, See G5791 &lt;br&gt;\n",
            "entry":null
        }
    ]
}
</code></pre>

<h5><?php echo trans('api.strongs.results.modern'); ?></h5>

<h5><?php echo trans('api.strongs.results.modern_note'); ?></h5>

<pre><code>{
    "errors":[],
    "error_level":0,
    "results":[
        {
            "id":11398,
            "number":"G2545",
            "root_word":"καίω",
            "pronunciation":"kah'-yo",
            "transliteration":"kaiō",
            "definition":"Apparently a primary verb; to *set on fire*, that is, *kindle* or (by implication) *consume:* - burn, light.",
            "short_definition":null,
            "is_special":0,
            "dictionary":"en_orig"
        },
        {
            "id":14471,
            "number":"G5719",
            "root_word":null,
            "pronunciation":null,
            "transliteration":null,
            "definition":"**Tense:** Present, See G5774 \n**Voice:** Active, See G5784 \n**Mood:** Indicative, See G5791",
            "short_definition":null,
            "is_special":1,
            "dictionary":"en_orig"
        }
    ]
}
</code></pre>
