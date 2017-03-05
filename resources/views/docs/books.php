<?php
    $context = 'api.books';
    $url = '/books';
    include( dirname(__FILE__) . '/generic.php');

    renderParameterHeader();
    renderCommonParameters(['language', 'callback']);
    renderParameterFooter();