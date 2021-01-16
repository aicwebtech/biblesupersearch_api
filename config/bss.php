<?php

/* Bible SuperSearch configs */
return array(
    'defaults' => array(
        'language'       => env('DEFAULT_LANGUAGE', 'English'),
        'language_short' => env('DEFAULT_LANGUAGE_SHORT', 'en'),
        'bible'          => env('DEFAULT_BIBLE', 'kjv'),
        'highlight_tag'  => env('DEFAULT_HIGHLIGHT_TAG', 'b'),
    ),
    'import_from_v2' => env('IMPORT_FROM_V2', FALSE),
    'daily_access_limit' => env('DAILY_ACCESS_LIMIT', 2000),

    'dev_tools' => env('ENABLE_DEV_TOOLS', FALSE),

    'pagination' => array(
        'limit' => 30,
    ),
    'context' => [
        'range' => 5
    ],
    'global_maximum_results' => 500,
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
        1 => array(
            'chapters' => 50,
        ),
        2 => array(
            'chapters' => 40,
        ),
        3 => array(
            'chapters' => 27,
        ),
        4 => array(
            'chapters' => 36,
        ),
        5 => array(
            'chapters' => 34,
        ),
        6 => array(
            'chapters' => 24,
        ),
        7 => array(
            'chapters' => 21,
        ),
        8 => array(
            'chapters' => 4,
        ),
        9 => array(
            'chapters' => 31,
        ),
        10 => array(
            'chapters' => 24,
        ),
        11 => array(
            'chapters' => 22,
        ),
        12 => array(
            'chapters' => 25,
        ),
        13 => array(
            'chapters' => 29,
        ),
        14 => array(
            'chapters' => 36,
        ),
        15 => array(
            'chapters' => 10,
        ),
        16 => array(
            'chapters' => 13,
        ),
        17 => array(
            'chapters' => 10,
        ),
        18 => array(
            'chapters' => 42,
        ),
        19 => array(
            'chapters' => 150,
        ),
        20 => array(
            'chapters' => 31,
        ),
        21 => array(
            'chapters' => 12,
        ),
        22 => array(
            'chapters' => 8,
        ),
        23 => array(
            'chapters' => 66,
        ),
        24 => array(
            'chapters' => 52,
        ),
        26 => array(
            'chapters' => 48,
        ),
        27 => array(
            'chapters' => 12,
        ),
        25 => array(
            'chapters' => 5,
        ),
        28 => array(
            'chapters' => 14,
        ),
        29 => array(
            'chapters' => 3,
        ),
        30 => array(
            'chapters' => 9,
        ),
        31 => array(
            'chapters' => 1,
        ),
        32 => array(
            'chapters' => 4,
        ),
        33 => array(
            'chapters' => 7,
        ),
        34 => array(
            'chapters' => 3,
        ),
        35 => array(
            'chapters' => 3,
        ),
        36 => array(
            'chapters' => 3,
        ),
        37 => array(
            'chapters' => 2,
        ),
        38 => array(
            'chapters' => 14,
        ),
        39 => array(
            'chapters' => 4,
        ),
        40 => array(
            'chapters' => 28,
        ),
        41 => array(
            'chapters' => 16,
        ),
        42 => array(
            'chapters' => 24,
        ),
        43 => array(
            'chapters' => 21,
        ),
        44 => array(
            'chapters' => 28,
        ),
        45 => array(
            'chapters' => 16,
        ),
        46 => array(
            'chapters' => 16,
        ),
        47 => array(
            'chapters' => 13,
        ),
        48 => array(
            'chapters' => 6,
        ),
        49 => array(
            'chapters' => 6,
        ),
        50 => array(
            'chapters' => 4,
        ),
        51 => array(
            'chapters' => 4,
        ),
        52 => array(
            'chapters' => 5,
        ),
        53 => array(
            'chapters' => 3,
        ),
        54 => array(
            'chapters' => 6,
        ),
        55 => array(
            'chapters' => 4,
        ),
        56 => array(
            'chapters' => 3,
        ),
        57 => array(
            'chapters' => 1,
        ),
        58 => array(
            'chapters' => 13,
        ),
        59 => array(
            'chapters' => 5,
        ),
        60 => array(
            'chapters' => 5,
        ),
        61 => array(
            'chapters' => 3,
        ),
        62 => array(
            'chapters' => 5,
        ),
        63 => array(
            'chapters' => 1,
        ),
        64 => array(
            'chapters' => 1,
        ),
        65 => array(
            'chapters' => 1,
        ),
        66 => array(
            'chapters' => 22,
        ),
    ]
);
