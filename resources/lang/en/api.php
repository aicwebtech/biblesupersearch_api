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
    
    'overview' => array(
        'name' => 'Overview',
    ),
    
    'bibles' => array(
        'name' => 'Bibles',
        'description' => 'Retrieves list of Bibles avaliable via the API',
        
    ),
    'books' => array(
        'name' => 'Books',
        'description' => 'Retrieves list of Bible Books',
        
    ),
    'statics' => array(
        'name' => 'Statics',
        'description' => 'Single API basic information needed by to use the API.  This includes a list of all avaliable Bibles, a list of bible books, '
        . 'the API version and API environment.',
        
    ),
);
