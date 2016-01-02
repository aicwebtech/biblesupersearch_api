<?php

// List of officially supported Bibles
// Used to initially populate the bibles table

// Todo - this needs to be placed in app/config. - Having trouble w this

return array(
        array(
            'module'    => 'kjv', 
            'shortname' => 'KJV', 
            'name'      => 'Authorized King James Version',
            'italics'   => 1,
            'year'      => '1611 / 1769'
        ),        
        array(
            'module'    => 'kjv_strongs', 
            'shortname' => 'KJV Strongs', 
            'name'      => 'KJV with Strongs',
            'italics'   => 1,
            'Strongs'   => 1,
            'year'      => '1611 / 1769'
        ),
        array(
            'module'    => 'tyndale', 
            'name'      => 'Tyndale Bible',
        ),
        array(
            'module'    => 'coverdale', 
            'name'      => 'Coverdale Bible',
            'year'      => '1535',
        ),
        array(
            'module'    => 'bishops', 
            'name'      => 'Bishops Bible',
            'year'      => '1568',
        ),
        array(
            'module'    => 'geneva', 
            'name'      => 'Geneva Bible',
            'year'      => '1587',
        ),
        array(
            'module'    => 'tr', 
            'shortname' => 'TR',
            'name'      => 'Textus Receptus NT',
            'year'      => '1550 / 1884',
            'lang'      => 'Greek',
            'lang_short'=> 'el',
        ),
        /*
        array(
            'module'    => 'tr_translit', 
            'name'      => 'Textus Receptus Transliterated*',
            'year'      => '1550 / 1884',
            'lang'      => 'Greek',
            'lang_short'=> 'el',
        ),
        */
        array(
            'module'    => 'trparsed', 
            'shortname' => 'TR Parsed',
            'name'      => 'Textus Receptus Parsed NT',
            'year'      => '1550 / 1884',
            'lang'      => 'Greek',
            'lang_short'=> 'el',
        ),
        array(
            'module'    => 'rv_1858', 
            'name'      => 'Reina Valera 1858 NT',
            'shortname' => 'RV 1858',
            'year'      => '1858',
            'lang'      => 'Spanish',
            'lang_short'=> 'es',
        ),
        array(
            'module'    => 'rv_1909', 
            'name'      => 'Reina Valera 1909',
            'shortname' => 'RV 1909',
            'year'      => '1909',
            'lang'      => 'Spanish',
            'lang_short'=> 'es',
        ),
        array(
            'module'    => 'sagradas', 
            'name'      => 'Sagradas Escrituras',
            'year'      => '1569',
            'lang'      => 'Spanish',
            'lang_short'=> 'es',
        ),
        array(
            'module'    => 'rvg', 
            'shortname' => 'RVG', 
            'name'      => 'Reina Valera Gómez',
            'year'      => '2004',
            'lang'      => 'Spanish',
            'lang_short'=> 'es',
            'copyright' => 1,
        ),
        array(
            'module'    => 'martin',
            'name'      => 'Martin',
            'year'      => '1744',
            'lang'      => 'French',
            'lang_short'=> 'fr',
        ),
        array(
            'module'    => 'epee',
            'name'      => "La Bible de l'Épée",
            'year'      => '2005',
            'lang'      => 'French',
            'lang_short'=> 'fr',
        ),
        array(
            'module'    => 'oster',
            'name'      => 'Ostervald',
            'year'      => '1996',
            'lang'      => 'French',
            'lang_short'=> 'fr',
        ),
        array(
            'module'    => 'afri',
            'shortname' => 'Afrikaans',
            'name'      => 'Afrikaans 1953',
            'year'      => '1953',
            'lang'      => 'Afrikanns',
            'lang_short'=> 'af',
        ),
        array(
            'module'    => 'svd',
            'shortname' => 'SVD',
            'name'      => 'Smith Van Dyke (Arabic)',
            'lang'      => 'Arabic',
            'lang_short'=> 'ar',
        ),
        /*
        array(
            'module'    => 'bkr',
            'shortname' => 'BKR',
            'name'      => 'Bible Kralicka (Czech)*'
        ),
        array(
            'module'    => 'stve',
            'name'      => 'Staten Vertaling (Dutch)'
        ),
        array(
            'module'    => 'finn',
            'name'      => 'Finnish 1776 (Finnish)'
        ),
        */
        array(
            'module'    => 'luther', 
            'name'      => 'Luther Bible',
            'year'      => '1545',
            'lang'      => 'German',
            'lang_short'=> 'de',
        ),
        /*
        array(
            'module'    => 'karoli',
            'name'      => 'Karoli (Hungarian)*'
        ),
        */
        array(
            'module'    => 'diodati',
            'name'      => 'Diodati',
            'year'      => '1649',
            'lang'      => 'Italian',
            'lang_short'=> 'it',
        ),
        /*
        array(
            'module'    => 'lith',
            'name'      => 'Lithuanian Bible (Lithuanian)*',
            'copyright' => 1,
        ),
        array(
            'module'    => 'maori',
            'name'      => 'Maori Bible (Maori)*'
        ),
        array(
            'module'    => 'cornilescu',
            'name'      => 'Cornilescu (Romanian)'
        ),
        */
        array(
            'module'    => 'synodal',
            'name'      => 'Synodal',
            'year'      => '1876',
            'lang'      => 'Russian',
            'lang_short'=> 'ru',
        ),
        /*
        array(
            'module'    => 'thaikjv',
            'name'      => 'Thai KJV (Thai)*'
        )
        */
);