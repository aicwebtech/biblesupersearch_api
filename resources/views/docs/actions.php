<div id='actions_tabs'>
    <ul>
        <li><a href='#tab_query'><?php echo trans('query.name') ?></a></li>
        <li><a href='#tab_statics'><?php echo trans('api.statics.name') ?></a></li>
        <li><a href='#tab_bibles'><?php echo trans('api.bibles.name') ?></a></li>
        <li><a href='#tab_books'><?php echo trans('api.books.name') ?></a></li>
        <li><a href='#tab_version'><?php echo trans('api.version.name') ?></a></li>
        <li><a href='#tab_strongs'><?php echo trans('api.strongs.name') ?></a></li>

        <?php if(config('download.enable')): ?>
            <li><a href='#tab_act_downloads'><?php echo trans('api.download.name') ?></a></li>
        <?php endif; ?>
    </ul>

    <div id='tab_query'>
        <?php include(dirname(__FILE__) . '/query.php'); ?>
    </div>
    <div id='tab_statics'>
        <?php include(dirname(__FILE__) . '/statics.php'); ?>
    </div>
    <div id='tab_bibles'>
        <?php include(dirname(__FILE__) . '/bibles.php'); ?>
    </div>
    <div id='tab_books'>
        <?php include(dirname(__FILE__) . '/books.php'); ?>
    </div>
    <div id='tab_strongs'>
        <?php include(dirname(__FILE__) . '/strongs.php'); ?>
    </div>
    <div id='tab_version'>
        <?php include(dirname(__FILE__) . '/version.php'); ?>
    </div>
    <?php if(config('download.enable')): ?>
        <div id='tab_act_downloads'>
            <?php include(dirname(__FILE__) . '/action_download.php'); ?>
        </div>
    <?php endif; ?>
</div>

