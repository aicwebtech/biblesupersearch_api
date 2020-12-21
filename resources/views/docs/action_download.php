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

    $download_limit = config('download.bible_limit');
?>
<br />

<div>NOTE: Specifying multiple values or ALL for both 'bible' and 'format' will result in an error.</div>

<?php if($download_limit): ?>
    <br /><div>NOTE: You may download a <b>maximum</b> of <?php echo $download_limit ?> Bibles from this API at once.</div>
<?php endif; ?>

<br /><br />

<table class='parameters' cellspacing="0">
    <tr><th colspan="4">Download Format Options</th></tr>
    <tr>
        <th>Group</th>
        <th>Identifier</th>
        <th>Name</th>
        <th>Description</th>
    </tr>

    <?php foreach($formats as $id => $format) : ?>
        <tr>
            <td><b><?php echo $format['name']; ?></b></td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td><?php echo $format['desc']; ?></td>
        </tr>

        <?php foreach($format['renderers'] as $rid => $r): ?>
            <tr>
                <td>&nbsp;</td>
                <td><?php echo $rid; ?></td>
                <td><?php echo $r['name']; ?></td>
                <td><?php echo $r['desc']; ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
</table>

<br /><br />
<h2>Download Formats</h2>

<?php foreach($formats as $id => $format) : ?>
    <table class='parameters' cellspacing="0">
<!--         <tr><th colspan='3'><?php echo $format['name']; ?></th></tr>
        <tr><th colspan='3'><?php echo $format['desc']; ?></th></tr> -->


        <tr>
            <th colspan='1'><?php echo $format['name']; ?></th>
            <th colspan='2'><?php echo $format['desc']; ?></th>
        </tr>

        <tr>
            <th>Identifier</th>
            <th>Name</th>
            <th>Description</th>
        </tr>

        <?php foreach($format['renderers'] as $rid => $r): ?>
            <tr>
                <td style='width: 20%'><?php echo $rid; ?></td>
                <td style='width: 40%'><?php echo $r['name']; ?></td>
                <td style='width: 40%'><?php echo $r['desc']; ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <br /><br />
<?php endforeach; ?>

<h4>Tip: Use the 'statics' API action to get this list of download formats for your application.</h4>

<br /><br />

<div>
    By default, this API action returns a file, and not the standard JSON-encoded response. <br />
    However, if an error occurs, the usual JSON structure will be returned, including the error messages.
</div>