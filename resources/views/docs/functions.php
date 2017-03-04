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
        <br /><br />
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
        </table> 
    <?php
}

function renderCommonParameters() {
    $params = array(
        'callback'
    );
    
    renderParameters($params, 'common');
}