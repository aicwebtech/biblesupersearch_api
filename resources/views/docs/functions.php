<?php

function renderParameters($parameters, $context) {
    foreach($parameters as $parameter) {
        ?>
            <tr>
                <td valign='top'><?php echo $parameter ?></td>
                <td valign='top'><?php echo trans($context . '.params.' . $parameter . '.name') ?></td>
                <td valign='top'><?php echo trans($context . '.params.' . $parameter . '.type') ?></td>
                <td valign='top'><?php echo trans($context . '.params.' . $parameter . '.default') ?></td>
                <td valign='top'><?php echo trans($context . '.params.' . $parameter . '.description') ?></td>
            </tr>
        <?php
    }
}

function renderParameterHeader($label = 'parameters') {
    ?>
        <br />
        <table class='parameters' cellspacing="0">
            <tr><th colspan='5'><?php echo trans('api.' . $label); ?></td></tr>
            <tr>
                <th><?php echo trans('api.parameter')?></th>
                <th><?php echo trans('api.name')?></th>
                <th><?php echo trans('api.type')?></th>
                <th><?php echo trans('api.default')?></th>
                <th><?php echo trans('api.description')?></th>
            </tr>
    <?php
}

function renderParameterFooter() {
    ?>
        </table><br />
    <?php
}

function renderCommonParameters($params) {
    renderParameters($params, 'api.common');
}

function renderActionHeader($action, $url = NULL) {
    $context = $action;
    $server = getServerUrl();
    $url = ($url === NULL) ? '/' . $action : '/' . $url;

    ?>
    <table>
        <tr>
            <td><?php echo trans('api.action') ?>: </td><td><?php echo trans($context . '.name') ?></td>
        </tr>
        <tr>
            <td>URL: </td><td><?php echo $server ?>/api<?php echo $url; ?></td>
        </tr>
        <tr>
            <td valign='top'>Description:</td><td><?php echo trans($context . '.description') ?></td>
        </tr>
    </table>
    <?php
}

function getServerUrl() {
    $http   = (array_key_exists('HTTPS', $_SERVER) && !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
    $server = (array_key_exists('SERVER_NAME', $_SERVER) && !empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : 'biblesupersearch.com';
    $server = $http . $server;
    return $server;
}