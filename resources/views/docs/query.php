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
        //'search_regexp',  // Future feature
        //'search_boolean', // Future feature
    );

    renderParameterHeader();
    renderParameters($params, $context);
    renderCommonParameters(['callback']);
    renderParameterFooter();

    ?><div><?php echo trans('query.advanced') ?>:</div><?php

    renderParameterHeader('advanced_parameters');
    renderParameters($advanced_params, $context);
    renderParameterFooter();

    include( dirname(__FILE__) . '/query_structures.php');
