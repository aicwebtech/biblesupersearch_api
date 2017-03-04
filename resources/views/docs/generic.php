<?php
    global $context, $url, $params;
    $context = ($context) ? $context : 'query';
    $http = (array_key_exists('HTTPS', $_SERVER) && !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
?>

<!--<?php echo $context ?>-->
<table>
    <tr>
        <td><?php echo trans('api.action') ?>: </td><td><?php echo trans($context . '.name') ?></td>
    </tr>
    <tr>
        <td>URL: </td><td><?php echo $http . $_SERVER['SERVER_NAME'] ?>/api<?php echo $url; ?></td>
    </tr>
    <tr>
        <td>Description:</td><td><?php echo trans($context . '.description') ?></td>
    </tr>
</table>


