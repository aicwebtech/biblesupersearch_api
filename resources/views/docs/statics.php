<?php
    $context = 'api.statics';
    $url = '/statics';
    include( dirname(__FILE__) . '/generic.php');

    renderParameterHeader();
    renderCommonParameters(['language', 'callback']);
    renderParameterFooter();
    
