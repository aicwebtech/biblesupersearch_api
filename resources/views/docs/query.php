<?php
    $context = 'query';
    $url = '';
    include( dirname(__FILE__) . '/generic.php');
    
    $params = array(
        'reference',
        'search',
        'bible',
        'whole_words',
        'exact_case',
        'data_format',
        'highlight',
        'highlight_tag',
        'search_type',
        'proximity_limit',
    );
    
    $advanced_params = array(
        'search_all',
        'search_any',
        'search_one',
        'search_none',
        'search_phrase',
        'search_regexp',
        'search_boolean',
    );
    
    renderParameterHeader();
    renderParameters($params, $context);
    renderParameterFooter();
    
    renderParameterHeader('advanced_parameters');
    renderParameters($advanced_params, $context);
    renderParameterFooter();
    