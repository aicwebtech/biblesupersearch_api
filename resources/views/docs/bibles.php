<?php
    $context = 'api.bibles';
    $url = '/bibles';
    include( dirname(__FILE__) . '/generic.php');

    renderParameterHeader();
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
        // <?php echo trans('api.bibles.indexed_by_module') , PHP_EOL ?>
        "kjv":{
            "name":"Authorized King James Version",
            "shortname":"KJV",
            "module":"kjv",         // <?php echo trans('api.bible_fields.module_desc') , PHP_EOL ?>
            "year":"1611 \/ 1769",  // <?php echo trans('api.bible_fields.year_desc') , PHP_EOL ?>
            "lang":"English",
            "lang_short":"en",
            "copyright":"0",        // <?php echo trans('api.bible_fields.copyright_desc') , PHP_EOL ?>
            "italics":"1",          // <?php echo trans('api.bible_fields.italics_desc') , PHP_EOL ?>
            "strongs":"0",          // <?php echo trans('api.bible_fields.strongs_desc') , PHP_EOL ?>
            "rank":"10",            // <?php echo trans('api.bible_fields.rank_desc') , PHP_EOL ?>
            "research":"0"          // <?php echo trans('api.bible_fields.research_desc_short') , PHP_EOL ?>
        },
        "geneva":{
            "name":"Geneva Bible",
            "shortname":"Geneva",
            "module":"geneva",
            "year":"1587",
            "lang":"English",
            "lang_short":"en",
            "copyright":"0",
            "italics":"0",
            "strongs":"0",
            "rank":"60",
            "research":"0"
        },
        "tr":{
            "name":"Textus Receptus NT",
            "shortname":"TR","
            "module":"tr",
            "year":"1550 \/ 1884",
            "lang":"Greek",
            "lang_short":"el","
            copyright":"0",
            "italics":"0",
            "strongs":"0",
            "rank":"70",
            "research":"0"
        },
        "wlc":{
            "name":"WLC",
            "shortname":"Wlc",
            "module":"wlc",
            "year":"",
            "lang":"Hebrew",
            "lang_short":"he",
            "copyright":"0",
            "italics":"0",
            "strongs":"0",
            "rank":"300",
            "research":"1"
        }
    }
}
</code></pre>
