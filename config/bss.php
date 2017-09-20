<?php

/* Bible SuperSearch configs */
return array(
    'defaults' => array(
        'language'       => env('DEFAULT_LANGUAGE', 'English'),
        'language_short' => env('DEFAULT_LANGUAGE_SHORT', 'en'),
        'bible'          => env('DEFAULT_BIBLE', 'kjv'),
    ),
    'import_from_v2' => env('IMPORT_FROM_V2', FALSE),
    'daily_access_limit' => env('DAILY_ACCESS_LIMIT', 2000),

    'pagination' => array(
        'limit' => 30,
    ),
    'global_maximum_results' => 500,
    // List of all search types the API supports
    'search_types' => [
        [
            'label' => 'All Words',
            'value' => 'and'
        ],
        [
            'label' => 'Any Word',
            'value' => 'or'
        ],
        [
            'label' => 'Exact Phrase',
            'value' => 'phrase'
        ],
        [
            'label' => 'Only One Word',
            'value' => 'xor'
        ],
        [
            'label' => 'Words Within 5 Verses',
            'value' => 'proximity'
        ],
        [
            'label' => 'Words Within Same Chapter',
            'value' => 'chapter'
        ],
        [
            'label' => 'Boolean Expression',
            'value' => 'boolean'
        ],
        [
            'label' => 'Regular Expression',
            'value' => 'regexp'
        ],
    ]
);
