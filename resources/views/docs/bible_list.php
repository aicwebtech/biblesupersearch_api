<table class='parameters' cellspacing="0">
    <tr><th colspan='7'><?php echo trans('api.bibles_avail'); ?></th></tr>
    <tr>
        <th><?php echo trans('api.bible_fields.module') ?>*</th>
        <th><?php echo trans('api.bible_fields.lang') ?></th>
        <th><?php echo trans('api.bible_fields.name') ?></th>
        <th><?php echo trans('api.bible_fields.shortname') ?></th>
        <th><?php echo trans('api.bible_fields.year') ?></th>
        <th><?php echo trans('api.bible_fields.copyright') ?></th>
        <th><?php echo trans('api.bible_fields.research') ?>**</th>
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

        </tr>
    <?php endforeach; ?>

</table>

<br />
<div>* <?php echo trans('api.bible_fields.module_desc') ?></div>
<div>** <?php echo trans('api.bible_fields.research_desc') ?></div>
