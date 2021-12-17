<?php

/* Error messages */

return array(
    'no_query'  => 'No query terms provided. Please specify search term(s) and/or passage reference(s).',
    'no_results' => 'Your search produced no results.',
    'triple_request' => 'Cannot use request, search and reference at the same time.  Please inform webmaster of UI design error.',
    'bible_no_results' => 'Your search produced no results in \':module\'.',
    'parallel_bible_no_results' => 'Your search produced no results in \':module\'. However, verses from this Bible have been included for comparison.',
    'passage_not_found' => 'Your request for :passage produced no results.',
    'bible_no_exist' => 'Bible text not found: \':module\'',
    'no_bible_enabled' => 'No Bibles are enabled. Please contact site adminstrator.',
    'invalid_search' => array(
        'reference' => 'Your search for \':search\' is invalid, and appears to be a passage reference.',
        'general' => 'Your search for \':search\' is invalid.',
    ),
    'book' => array(
        'invalid_in_range' => 'Invalid book in book range: \':range\'.',
        'not_found' => 'Book not found: \':book\'',
        'multiple_without_search' => 'Cannot retrieve multiple books at once.',
    ),
    'operator' => array(
        'op_at_beginning' => 'Operators such as \':op\' cannot be at the beginning of your search. Please remove it, or use it\'s lower case equivalent.',
        'op_at_end' => 'Operators such as \':op\' cannot be at the end of your search. Please remove it, or use it\'s lower case equivalent.',
    ),
    'paren_mismatch' => 'Your parentheses are mismatched.',
    'prox_paren_mismatch' => 'Your parentheses are mismatched, or you have a proximity operator inside of parentheses.',
    'prox_operator_not_allowed' => 'Proximity operators such as PROX and CHAP can only be used with boolean searches',
    'hit_limit_reached' => 'Maximum hits has been reached for today for this domain / IP address',
    'access_revoked' => 'Your access to this API has been revoked.  Please contact system administrator.',
    'result_limit_reached' => 'Your search was limited to :maximum results.  Please refine your search if necessary.',
    'strongs_input_required' => 'Strong\'s Number required',
    'strongs_not_found' => 'Strong\'s Number not found',
    'to_many_download' => 'Too many Bibles requested for download.  You can request a maximum of :maximum Bibles at once.',
);
