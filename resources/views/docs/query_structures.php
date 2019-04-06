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
    "strongs":[], // <?php echo trans('query.strongs') , PHP_EOL ?>
    "paging":{
        "total":338,
        "per_page":30,
        "current_page":1,
        "last_page":12,
        "from":1,
        "to":30
    },
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
    "strongs":[], // <?php echo trans('query.strongs') , PHP_EOL ?>
    "results":[
        {
            "book_id":45,
            "book_name":"Romans",
            "book_short":"Rm",
            "book_raw":"Rom",            // <?php echo trans('query.params.data_format.passage.book_raw') , PHP_EOL ?>
            "chapter_verse":"1:1-2",     // <?php echo trans('query.params.data_format.passage.chapter_verse') , PHP_EOL ?>
            "nav":{ ... },               // <?php echo trans('query.params.data_format.passage.nav') , PHP_EOL ?>
            "chapter_verse_raw":"1:1-2", // <?php echo trans('query.params.data_format.passage.chapter_verse_raw') , PHP_EOL ?>
            "verse_index": {             // <?php echo trans('query.params.data_format.passage.verse_index') , PHP_EOL ?>
                1: [1, 2]
            },
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
            "nav": {ncb: 40, ncc: 6, pcb: 40, pcc: 4, ccb: 40, ccc: 5, nb: 41, pb: 39}
            "verse_index": {
                5: [6, 7, 8]
            },
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

<a name='navigation' />
<br /><br />

<h3>
    <?php echo trans('query.navigation.label')?>
</h3>

<h4>
    <?php echo trans('query.navigation.browsing.label')?>
</h4>
<p><?php echo trans('query.navigation.browsing.desc')?></p>

<pre><code>{
    "errors":[],
    "error_level":0,
    "results":[
        {
            "book_id":40,
            "book_name":"Matthew",
            "book_short":"Mt",
            "book_raw":"Matt",
            "chapter_verse":"5:6-8",
            "chapter_verse_raw":"5:6-8",
            "nav": {
                // Link to Next Chapter, if applicable
                next_chapter: "Matthew 6",  // 'reference' for next chapter link
                ncb: 40,    // Next chapter book: book_id of the chapter following this one
                ncc: 6,     // Next chapter chapter: chapter number of chapter following this one

                // Link to Previous Chapter, if applicable
                prev_chapter: "Matthew 4",  // 'reference' for previous chapter link
                pcb: 40,    // Previous chapter book: book_id of the chapter preceding this one
                pcc: 4,     // Previous chapter chapter: chapter number of the chapter preceding this one

                // Link to current chapter (if not already displaying the whole chapter)
                cur_chapter: "Matthew 5",   // 'reference' for current chapter link
                ccb: 40,    // Current chapter book: book_id of the current chapter
                ccc: 5,     // Current chapter chapter: chapter number of the current chapter,

                // Link to the Next Book, if applicable
                next_book: "Mark",          // 'reference' of next book
                nb: 41,     // Book_id of the next book

                // Link to the Previous Book, if applicable
                prev_book: "Malachi"        // 'reference' of previous book
                pb: 39      // Book_id of the previous book
            },
            "verse_index": {
                5: [6, 7, 8]
            },
            "verses":{ ... }
            "verses_count":3,
            "single_verse":false
        }
    ]
}
</code></pre>

<a name='pagination' />
<h4>
    <?php echo trans('query.navigation.pagination.label')?>
</h4>

<p><?php echo trans('query.navigation.pagination.desc')?></p>

<pre><code>{
    "errors":[],
    "error_level":0,
    "paging":{
        "total":338,        // Total number of results
        "per_page":30,      // Results per page
        "current_page":1,   // Current page number
        "last_page":12,     // Total number of pages
        "from":61,          // First displaying result  Example: 'Now displaying results 61 to 90'
        "to":90             // Last displaying result
    },
    "results":[ ... ]
}</code></pre>

