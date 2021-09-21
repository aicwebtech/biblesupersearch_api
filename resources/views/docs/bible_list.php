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
        <th class='col_module'><?php echo trans('api.bible_fields.module') ?>*</th>
        <th class='col_lang'><?php echo trans('api.bible_fields.lang') ?></th>
        <th class='col_name'><?php echo trans('api.bible_fields.name') ?></th>
        <th class='col_shortname'><?php echo trans('api.bible_fields.shortname') ?></th>
        <th class='col_year'><?php echo trans('api.bible_fields.year') ?></th>
        <th class='col_copyright'><?php echo trans('api.bible_fields.copyright') ?></th>
        <th class='col_research'><?php echo trans('api.bible_fields.research') ?>**</th>

        <?php if(config('download.enable')): ?>
            <th class='col_downloadable'><?php echo trans('api.bible_fields.downloadable') ?></th>
        <?php endif; ?>
    </tr>

    <?php if(is_array($bibles) && !empty($bibles)): ?>
        <?php foreach($bibles as $bible) : ?>
            <tr>
                <td class='col_module'><?php echo $bible['module'] ?></td>
                <td class='col_lang'><?php echo $bible['lang'] ?></td>
                <td class='col_name'><?php echo $bible['name'] ?></td>
                <td class='col_shortname'><?php echo $bible['shortname'] ?></td>
                <td class='col_year'><?php echo $bible['year'] ?></td>
                <td class='col_copyright'><?php echo $bible['copyright'] ? 'Yes' : 'No' ?></td>
                <td class='col_research'><?php echo $bible['research'] ? 'Yes' : 'No' ?></td>

                <?php if(config('download.enable')): ?>
                    <td class='col_downloadable'><?php echo $bible['downloadable'] ? 'Yes' : 'No' ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="8">-- No Bibles Enabled --</td></tr>
    <?php endif; ?>

</table>

<br />
<div>* <?php echo trans('api.bible_fields.module_desc') ?></div>
<div>** <?php echo trans('api.bible_fields.research_desc') ?></div>
