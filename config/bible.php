<?php

return [
    // Bible module names reserved for future use
    'reserved' => [
        'niv'           => 'New International Version',
        'niv_2011'      => 'New International Version (2011)',
        'niv_1984'      => 'New International Version (1984)',
        'nasb'          => 'New American Standard Bible',
        'nasb_updated'  => 'New American Standard Bible - Updated (1995)',
        'nkjv'          => 'New King James Version',
        'mev'           => 'Modern English Version',
        'esv'           => 'English Standard Version',
        'rsv'           => 'Revised Standard Version',
        'nrsv'          => 'New Revised Standard Version',
        'nlt'           => 'New Living Translation',
        'csb'           => 'Christian Standard Bible',
    ],
    
    // Bible modules needed installed and enabled for complete PHPUnit testing
    'testing' => [
        'kjv',
        'tyndale',
        'bishops',
        'luther',
        'tr',
        'web',
        // 'lith',
        'rvg',
        'diodati',
        'wlc',
        'svd',
        'thaikjv',
        'lv_gluck_8',
        'synodal',
        'martin',
        'geneva',
        'chinese_union_trad',
    ],
];
