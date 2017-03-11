<a name='query_structures' />

<h3>
    <?php echo trans('query.params.data_format.name') . ' ' . trans('api.examples') ?>:
</h3>

<h5>
     <?php echo trans('query.params.reference.name') ?>: 'Rom 1:1-2; Matt 5:6-8'
</h5>

<h4>
    'minimal' <?php echo trans('api.data_structure') ?>:
</h4>

<div class='data_format'>
    <?php echo trans('query.params.data_format.raw.description') ?>
</div>

<pre><code>{
    "errors":[],
    "error_level":0,
    "results":{
        "kjv":[
            {"id":"23241","book":"40","chapter":"5","verse":"6","text":"Blessed are they which do hunger and thirst after righteousness: for they shall be filled."},
            {"id":"23242","book":"40","chapter":"5","verse":"7","text":"Blessed are the merciful: for they shall obtain mercy."},
            {"id":"23243","book":"40","chapter":"5","verse":"8","text":"Blessed are the pure in heart: for they shall see God."},
            {"id":"27932","book":"45","chapter":"1","verse":"1","text":"Paul, a servant of Jesus Christ, called to be an apostle, separated unto the gospel of God,"},
            {"id":"27933","book":"45","chapter":"1","verse":"2","text":"(Which he had promised afore by his prophets in the holy scriptures,)"}
        ]
    }
}
</code></pre>

<h4>
    'passage' <?php echo trans('api.data_structure') ?>:
</h4>

<div class='data_format'>
    <?php echo trans('query.params.data_format.passage.description') ?>
</div>

<pre><code>{
    "errors":[],
    "error_level":0,
    "results":[
        {
            "book_id":45,
            "book_name":"Romans",
            "book_short":"Rm",
            "book_raw":"Rom",            // <?php echo trans('query.params.data_format.passage.book_raw') , PHP_EOL ?>
            "chapter_verse":"1:1-2",     // <?php echo trans('query.params.data_format.passage.chapter_verse') , PHP_EOL ?>
            "chapter_verse_raw":"1:1-2", // <?php echo trans('query.params.data_format.passage.chapter_verse_raw') , PHP_EOL ?>
            "verses":{
                // <?php echo trans('query.params.data_format.passage.id_bible') , PHP_EOL ?>
                "kjv":{
                    // <?php echo trans('query.params.data_format.passage.id_chapter') , PHP_EOL ?>
                    "1":{
                        // <?php echo trans('query.params.data_format.passage.id_verse') , PHP_EOL ?>
                        "1":{"id":"27932","book":"45","chapter":"1","verse":"1","text":"Paul, a servant of Jesus Christ, called to be an apostle, separated unto the gospel of God,"},
                        "2":{"id":"27933","book":"45","chapter":"1","verse":"2","text":"(Which he had promised afore by his prophets in the holy scriptures,)"}
                    }
                }
            },
            "verses_count":2,
            "single_verse":false  // <?php echo trans('query.params.data_format.passage.single') , PHP_EOL ?>
        },
        {
            "book_id":40,
            "book_name":"Matthew",
            "book_short":"Mt",
            "book_raw":"Matt",
            "chapter_verse":"5:6-8",
            "chapter_verse_raw":"5:6-8",
            "verses":{
                "kjv":{
                    "5":{
                        "6":{"id":"23241","book":"40","chapter":"5","verse":"6","text":"Blessed are they which do hunger and thirst after righteousness: for they shall be filled."},
                        "7":{"id":"23242","book":"40","chapter":"5","verse":"7","text":"Blessed are the merciful: for they shall obtain mercy."},
                        "8":{"id":"23243","book":"40","chapter":"5","verse":"8","text":"Blessed are the pure in heart: for they shall see God."}
                    }
                }
            },
            "verses_count":3,
            "single_verse":false
        }
    ]
}
</code></pre>