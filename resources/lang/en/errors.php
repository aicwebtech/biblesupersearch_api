<?php

/* Error messages */

return array(
    'no_query'  => 'No query terms provided. Please specify search term(s) and/or passage reference(s).',
    'no_results' => 'Your search produced no results.',
    'bible_no_results' => 'Your search produced no results in \':module\'.',
    'passage_not_found' => 'Your request for :passage produced no results.',
    'bible_no_exist' => 'Bible text \':module\' not found.',
    'no_bible_enabled' => 'No Bibles are enabled. Please contact site adminstrator',
    'book' => array(
        'invalid_in_range' => 'Invalid book in book range: \':range\'.',
        'not_found' => 'Book \':book\' not found.',
        'multiple_without_search' => 'Cannot retrieve multiple books at once.',
    ),
    'paren_mismatch' => 'Your parenthenses are mismatched.',
    'prox_paren_mismatch' => 'Your parenthenses are mismatched, or you have a proximity operator inside of parentheses.',
    'prox_operator_not_allowed' => 'Proximity operators such as PROX and CHAP can only be used with boolean searches',
);
