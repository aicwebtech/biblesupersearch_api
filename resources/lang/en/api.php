<?php

/* API Docs */

return array(
    'action' => 'Action',
    'description' => 'Description',
    'parameters' => 'Action Parameters',
    'advanced_parameters' => 'Advanced Parameters',
    'name' => 'Name',
    'type' => 'Type',
    'default' => 'Default',
    'parameter' => 'Parameter',
    'examples' => 'Examples',
    'data_structure' => 'Data Structure',
    'bible_list' => 'Bible List',

    'bible_fields' => array(
        'module' => 'Module',
        'module_desc' => 'The \'module\' is what the API uses to identify the Bible for queries.',
        'lang' => 'Language',
        'name' => 'Name',
        'shortname' => 'Short Name',
        'year' => 'Year',
        'copyright' => 'Copyrighted',
        'research' => 'Research',
        'research_desc' => 'Indicates a Bible text that does not nessessarily adhere to the traditional Textus Receptus Greek and Masoretic Hebrew.'
        . ' These texts are intended for research purposes only.',
    ),

    'overview' => array(
        'name' => 'Overview',
        'description' => 'This API allows the Bible SuperSearch Bible search engine to be used seamlesly on any website or app. <br /><br />'
            . 'There is no cost to use the API, however, a website will be limited to ' . config('bss.daily_access_limit') . ' hits per day.<br /><br />',
        'all_actions' => 'All API Actions',
        'see_in_action' => 'See API in action here',
        'bullets' => array(
            'format' => 'Return a JSON-encoded string',
            'cors' => 'Can be used cross-domain (By sending the CORS header: \'Access-Control-Allow-Origin: *\')',
            'jsonp' => 'Support JSONP via the \'callback\' parameter',
            'structure' => 'Return this basic structure',
        ),
        'structure' => array(
            'errors' => 'Array of error messages, if any.  If an error has occured, the API will return HTTP status code 400.',
            'error_level' => 'Integer indicating error level.  0 - No error, 1 & 2 - Reserved for future use, 3 - Non-fatal error,  4 - Fatal error ',
            'results' => 'Contains the actual data returned by the API',
        )
    ),

    'bibles' => array(
        'name' => 'Bibles',
        'description' => 'Retrieves the list of Bibles avaliable via the API',

    ),
    'books' => array(
        'name' => 'Books',
        'description' => 'Retrieves a list of Bible Books in the specified language',

    ),
    'statics' => array(
        'name' => 'Statics',
        'description' => 'Single API action to retrieve basic information needed to use the API. <br/><br />'
        . 'This includes: <ul>'
        . '<li>A list of all avaliable Bibles</li>'
        . '<li>A list of Bible books in the specified language</li>'
        . '<li>The API version</li>'
        . '<li>The API environment (production/beta/development)</li>'
        . '</ul>',

    ),
    'common' => array(
        'params' => array(
            'callback' => array(
                'name' => 'Call back',
                'description' => 'Name of callback for JSONP, if needed',
                'default' => '(none)',
                'type' => 'String',
            ),
            'language' => array(
                'name' => 'Language',
                'description' => '2 Character ISO 639‑1 language code',
                'default' => config('app.locale'),
                'type' => 'String',
            ),
        )
    )
);