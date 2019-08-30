<?php
    $context = 'api.download';
    $url = '/download';
    renderActionHeader($context, $url);
    renderParameterHeader();

    $params = array(
        'bible',
        'format',
        'zip',
    );

    renderParameters($params, $context);
    renderParameterFooter();
?>
<br />

<div>NOTE: Specifying multiple values or ALL for both 'bible' and 'format' will result in an error.</div>
<br /><br />

<table class='parameters' cellspacing="0">
    <tr><th colspan="3">Download Format Options</th></tr>
    <tr>
        <th>Identifier</th>
        <th>Name</th>
        <th>Description</th>
    </tr>

    <?php foreach($formats as $id => $format) : ?>
        <tr>
            <td><?php echo $id; ?></td>
            <td><?php echo $format['name']; ?></td>
            <td><?php echo $format['desc']; ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h4>Tip: Use the 'statics' API action to get this list of download formats for your application.</h4>

<br /><br />

<div>
    By default, this API action returns a file, and not the standard JSON-encoded response. <br />
    However, if an error occurs, the usual JSON structure will be returned, including the error messages.
</div>