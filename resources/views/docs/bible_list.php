<?php
    $bible_table_colspan = 7;

    if(config('download.enable')) {
        $bible_table_colspan ++;
    }
?>

<table class='parameters' cellspacing="0">
    <tr>
        <th colspan='<?php echo $bible_table_colspan ?>'><?php echo trans('api.bibles_avail'); ?></th>
    </tr>
    <tr>
        <th><?php echo trans('api.bible_fields.module') ?>*</th>
        <th><?php echo trans('api.bible_fields.lang') ?></th>
        <th><?php echo trans('api.bible_fields.name') ?></th>
        <th><?php echo trans('api.bible_fields.shortname') ?></th>
        <th><?php echo trans('api.bible_fields.year') ?></th>
        <th><?php echo trans('api.bible_fields.copyright') ?></th>
        <th><?php echo trans('api.bible_fields.research') ?>**</th>

        <?php if(config('download.enable')): ?>
            <th><?php echo trans('api.bible_fields.downloadable') ?></th>
        <?php endif; ?>
    </tr>

    <?php foreach($bibles as $bible) : ?>
        <tr>
            <td><?php echo $bible['module'] ?></td>
            <td><?php echo $bible['lang'] ?></td>
            <td><?php echo $bible['name'] ?></td>
            <td><?php echo $bible['shortname'] ?></td>
            <td><?php echo $bible['year'] ?></td>
            <td><?php echo $bible['copyright'] ? 'Yes' : 'No' ?></td>
            <td><?php echo $bible['research'] ? 'Yes' : 'No' ?></td>

            <?php if(config('download.enable')): ?>
            <td><?php echo $bible['downloadable'] ? 'Yes' : 'No' ?></td>
            <?php endif; ?>


        </tr>
    <?php endforeach; ?>

</table>

<br />
<div>* <?php echo trans('api.bible_fields.module_desc') ?></div>
<div>** <?php echo trans('api.bible_fields.research_desc') ?></div>
