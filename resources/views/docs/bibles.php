<?php
    $context = 'api.bibles';
    $url = '/bibles';
    include( dirname(__FILE__) . '/generic.php');

    renderParameterHeader();
    renderCommonParameters(['callback']);
    renderParameterFooter();