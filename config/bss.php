<?php

/* Bible SuperSearch configs */
return [
    'defaults' => [
        'language'       => env('DEFAULT_LANGUAGE', 'English'),
        'language_short' => env('DEFAULT_LANGUAGE_SHORT', 'en'),
        'bible'          => env('DEFAULT_BIBLE', 'kjv'),
        'highlight_tag'  => env('DEFAULT_HIGHLIGHT_TAG', 'b'),
    ],
    'import_from_v2' => env('IMPORT_FROM_V2', FALSE),
    'daily_access_limit' => env('DAILY_ACCESS_LIMIT', 2000),

    'dev_tools' => env('ENABLE_DEV_TOOLS', FALSE),

    'pagination' => [
        'limit' => 30,
    ],
    'context' => [
        'range' => 5
    ],
    
    // Maximum number of verses that can be displayed at once
    'global_maximum_results' => 500, 
    // Maximum number of verses returned by parallel search, displayed or not
    'parallel_search_maximum_results' => 2000, 

    // List of all search types the API supports
    'search_types' => [
        [
            'label' => 'All Words',
            'value' => 'and',
            'bool'  => FALSE, // Whether to allow boolean / proximity operators on this search type
        ],
        [
            'label' => 'Any Word',
            'value' => 'or',
            'bool'  => FALSE,
        ],
        [
            'label' => 'Exact Phrase',
            'value' => 'phrase',
            'bool'  => FALSE,
        ],
        [
            'label' => 'Only One Word',
            'value' => 'xor',
            'bool'  => FALSE,
        ],
        [
            'label' => 'Two or More Words',
            'value' => 'two_or_more',
            'bool'  => FALSE,
        ],
        [
            'label' => 'Words Within 5 Verses',
            'value' => 'proximity',
            'bool'  => FALSE,
        ],
        [
            'label' => 'Words Within Same Chapter',
            'value' => 'chapter',
            'bool'  => FALSE,
        ],        
        [
            'label' => 'Words Within Same Book',
            'value' => 'book',
            'bool'  => FALSE,
        ],
        [
            'label' => 'Boolean Expression',
            'value' => 'boolean',
            'bool'  => TRUE,
        ],
        [
            'label' => 'Regular Expression',
            'value' => 'regexp',
            'bool'  => FALSE,
        ],
    ],
    'books_common' => [
        1 => [
            'chapters' => 50,
        ],
        2 => [
            'chapters' => 40,
        ],
        3 => [
            'chapters' => 27,
        ],
        4 => [
            'chapters' => 36,
        ],
        5 => [
            'chapters' => 34,
        ],
        6 => [
            'chapters' => 24,
        ],
        7 => [
            'chapters' => 21,
        ],
        8 => [
            'chapters' => 4,
        ],
        9 => [
            'chapters' => 31,
        ],
        10 => [
            'chapters' => 24,
        ],
        11 => [
            'chapters' => 22,
        ],
        12 => [
            'chapters' => 25,
        ],
        13 => [
            'chapters' => 29,
        ],
        14 => [
            'chapters' => 36,
        ],
        15 => [
            'chapters' => 10,
        ],
        16 => [
            'chapters' => 13,
        ],
        17 => [
            'chapters' => 10,
        ],
        18 => [
            'chapters' => 42,
        ],
        19 => [
            'chapters' => 150,
        ],
        20 => [
            'chapters' => 31,
        ],
        21 => [
            'chapters' => 12,
        ],
        22 => [
            'chapters' => 8,
        ],
        23 => [
            'chapters' => 66,
        ],
        24 => [
            'chapters' => 52,
        ],
        26 => [
            'chapters' => 48,
        ],
        27 => [
            'chapters' => 12,
        ],
        25 => [
            'chapters' => 5,
        ],
        28 => [
            'chapters' => 14,
        ],
        29 => [
            'chapters' => 3,
        ],
        30 => [
            'chapters' => 9,
        ],
        31 => [
            'chapters' => 1,
        ],
        32 => [
            'chapters' => 4,
        ],
        33 => [
            'chapters' => 7,
        ],
        34 => [
            'chapters' => 3,
        ],
        35 => [
            'chapters' => 3,
        ],
        36 => [
            'chapters' => 3,
        ],
        37 => [
            'chapters' => 2,
        ],
        38 => [
            'chapters' => 14,
        ],
        39 => [
            'chapters' => 4,
        ],
        40 => [
            'chapters' => 28,
        ],
        41 => [
            'chapters' => 16,
        ],
        42 => [
            'chapters' => 24,
        ],
        43 => [
            'chapters' => 21,
        ],
        44 => [
            'chapters' => 28,
        ],
        45 => [
            'chapters' => 16,
        ],
        46 => [
            'chapters' => 16,
        ],
        47 => [
            'chapters' => 13,
        ],
        48 => [
            'chapters' => 6,
        ],
        49 => [
            'chapters' => 6,
        ],
        50 => [
            'chapters' => 4,
        ],
        51 => [
            'chapters' => 4,
        ],
        52 => [
            'chapters' => 5,
        ],
        53 => [
            'chapters' => 3,
        ],
        54 => [
            'chapters' => 6,
        ],
        55 => [
            'chapters' => 4,
        ],
        56 => [
            'chapters' => 3,
        ],
        57 => [
            'chapters' => 1,
        ],
        58 => [
            'chapters' => 13,
        ],
        59 => [
            'chapters' => 5,
        ],
        60 => [
            'chapters' => 5,
        ],
        61 => [
            'chapters' => 3,
        ],
        62 => [
            'chapters' => 5,
        ],
        63 => [
            'chapters' => 1,
        ],
        64 => [
            'chapters' => 1,
        ],
        65 => [
            'chapters' => 1,
        ],
        66 => [
            'chapters' => 22,
        ],
    ]
];
