<?php

/* API Docs */

return array(
    'action' => 'Action',
    'usage' => 'Usage',
    'see' => 'See',
    'results' => 'Results',
    'description' => 'Description',
    'parameters' => 'Action Parameters',
    'advanced_parameters' => 'Advanced Parameters',
    'name' => 'Name',
    'type' => 'Type',
    'default' => 'Default',
    'parameter' => 'Parameter',
    'examples' => 'Examples',
    'example' => 'Example',
    'data_structure' => 'Data Structure',
    'bible_list' => 'Bible List',
    'bibles_avail' => 'Bibles Available via API',
    'tos' => 'Terms of Service',
    'privacy' => 'Privacy Policy',
    'download' => 'Download',
    'free_download' => 'Free Downloads',

    'bible_fields' => array(
        'module' => 'Module',
        'module_desc' => 'The \'module\' is what the API uses to identify the Bible for queries.',
        'lang' => 'Language',
        'name' => 'Name',
        'shortname' => 'Short Name',
        'year' => 'Year',
        'year_desc' => 'Publication year',
        'copyright' => 'Copyrighted',
        'copyright_desc' => 'Copyrighted (not public domain)',
        'rank_desc' => 'Default sorting order.',
        'italics_desc' => '(Future use) Supports Italicised words ',
        'strongs_desc' => '(Future use) Embedded strongs numbers',
        'downloadable' => 'Downloadable',
        'research' => 'Research',
        'research_desc' => 'Indicates a Bible text that does not nessessarily adhere to the traditional Textus Receptus Greek and Masoretic Hebrew.'
        . ' These texts are intended for research purposes only.',
        'research_desc_short' => 'Indicates a Bible text intended for research purposes only.',
    ),

    'overview' => array(
        'name' => 'Overview',
        'description' => 'This webservice API allows the Bible SuperSearch Bible search engine to be used seamlesly on any website or app. <br /><br />'
            . 'There is no cost to use the API, however, your website will be limited to :limit hits per day.<br /><br />',
        'all_actions' => 'All API Actions',
        'see_in_action' => 'See API in action here',
        'bullets' => array(
            'format' => 'Return a JSON-encoded string',
            'cors' => 'Can be used cross-domain',
            'jsonp' => 'Support JSONP via the \'callback\' parameter',
            'structure' => 'Return this basic structure',
            'method' => 'Should be called using GET',
        ),
        'structure' => array(
            'errors' => 'Array of error messages, if any.  If an error has occured, the API will return HTTP status code 400.',
            'error_level' => 'Integer indicating error level.  0 - No error, 1 & 2 - Reserved for future use, 3 - Non-fatal error,  4 - Fatal error ',
            'results' => 'Contains the actual data returned by the API',
        ),
        'official' => array(
            'label' => 'Linking Official Bible SuperSearch Applications to this API:',
            'desc'  => '',
            'it_should_look_like_this' => 'It should look like this: ',

            'client' => array(
                'label' => 'Universal Client',
                'desc'  => 'In your config.js file, set "apiUrl" to',
                'desc2' => 'Now, save the file and reload the application to make sure it works.',
            ),
            'wp' => array(
                'label' => 'WordPress Plugin',
                'desc1' => 'On the admin side, navigate to "Settings", then to "Bible SuperSearch:"',
                'desc2' => 'Now, click on the Advanced tab:',
                'desc3' => 'Now, change the API URL to: ',
                'desc4' => 'If you\'ve entered the URL correctly, the box will turn green.  Now, click "Save Changes:"',
                'desc5' => 'Now, reload the application and it should be working off of this API.'
            ),
        )
    ),

    'bibles' => array(
        'name' => 'Bibles',
        'description' => 'Retrieves the list of Bibles avaliable via the API',
        'indexed_by_module' => 'Indexed by \'module\'',
    ),
    'books' => array(
        'name' => 'Books',
        'description' => 'Retrieves a list of Bible Books in the specified language',
    ),
    'strongs' => array(
        'name' => 'Strong\'s Definitions',
        'description' => 'Retrieves definitions for the given Strong\'s numbers.',
        'params' => array(
            'strongs' => array(
                'type' => 'String',
                'name' => 'Strong\'s Number(s)',
                'default' => '(none)',
                'description' => 'Retrieve Strong\'s definitions for the given Strong\'s numbers. Can be a single string, a comma-separated string or a '
                . 'JSON-encoded array',
            ),
        ),
        'results' => [
            'tvm_note' => 'Note: Some Strong\'s numbers will return TVM (Tense / Voice / Mood) records.  Your app will need to be able to handle both.',
            'tvm' => 'TVM record, "tvm" will be populated, and other items will be empty.',
            'def' => 'Definition record, "tvm" will be empty.'
        ]
    ),
    'download' => array(
        'name' => 'Bible Download',
        'description' => 'Returns the given Bible module(s) as a file download, rendered into the selected format',
        'params' => array(
            'bible' => array(
                'type' => 'String - multiple',
                'name' => 'Bibles',
                'default' => '(none)',
                'description' => '(required) MODULE of the Bible(s) to download. Can be a string, array or JSON-encoded array. &nbsp;'
                . 'Set to ALL to get all downloadable Bibles'
            ),
            'format' => array(
                'type' => 'String - multiple',
                'name' => 'Format',
                'default' => '(none)',
                'description' => '(required) Format of the Bibles to download. Can be a string, array or JSON-encoded array. &nbsp;'
                . 'See list of options below.'
            ),
            'zip' => array(
                'type' => 'Boolean',
                'name' => 'ZIP',
                'default' => 'FALSE',
                'description' => 'If specified, forces a ZIP file download when one Bible or format is selected. &nbsp;'
                . 'Note: Selecting multiple Bibles or formats<br />automatially returns a ZIP file, and this parameter is ignored'
            ),
        ),
    ),
    'statics' => array(
        'name' => 'Statics',
        'description' => 'Single API action to retrieve basic information needed to use the API. <br/><br />'
        . 'This includes: <ul>'
        . '<li>A list of all avaliable Bibles</li>'
        . '<li>A list of Bible books in the specified language</li>'
        . '<li>A list of shortcuts in the specified language</li>'
        . '<li>A list of search types</li>'
        . '<li>A list of download formats</li>'
        . '<li>The API name</li>'
        . '<li>The API version</li>'
        . '<li>The API environment (production/beta/development)</li>'
        . '</ul>',
    ),
    'version' => array(
        'name' => 'Version',
        'description' => 'Retrieves basic version information about the API. <br/><br />'
        . 'This includes: <ul>'
        . '<li>The API name</li>'
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
