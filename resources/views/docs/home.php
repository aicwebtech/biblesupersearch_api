<!DOCTYPE html>
<html>
    <head>
        <title><?php echo trans('app.name') ?> <?php echo config('app.version'); ?></title>
        <link rel="stylesheet" href="/js/bin/jquery-ui/jquery-ui.css">
        <link rel="stylesheet" href="/js/bin/jquery-ui/jquery-ui.theme.css">
        <link rel="stylesheet" href="/css/docs.css">
        <script src='/js/bin/jquery/jquery-3.1.1.min.js'></script>
        <script src='/js/bin/jquery-ui/jquery-ui.js'></script>
        <script src='/js/docs.js'></script>
    </head>
    <?php
        $http = (array_key_exists('HTTPS', $_SERVER) && !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
        require_once( dirname(__FILE__) . '/functions.php');
        $my_url = getServerUrl();
    ?>
    <body>
        <div id='container'>
            <h1 class='hcenter'><?php echo trans('app.name') ?> <?php echo trans('app.documentation') ?></h1>
            <h2 class='hcenter'><?php echo trans('app.version') ?> <?php echo $version ?> <!-- this is the hardcoded app version --></h2>
            <?php if(config('app.env') != 'production'): ?>
                <h2 class='hcenter warning'><?php echo trans('app.env_warnings.' . config('app.env')) ?></h2>
            <?php endif; ?>

            <div id='tabs'>
                <ul>
                    <li><a href='#tab_overview'><?php echo trans('api.overview.name') ?></a></li>
                    <li><a href='#tab_list'><?php echo trans('api.bible_list') ?></a></li>
                    <li><a href='#tab_actions'><?php echo trans('api.usage') ?></a></li>

                    <?php if(config('download.enable')): ?>
                        <li><a href='#tab_downloads'><?php echo trans('api.free_download')?></a></li>
                    <?php endif; ?>

                    <li><a href='#tab_tos'><?php echo trans('api.tos') ?></a></li>
                    <li><a href='#tab_privacy'><?php echo trans('api.privacy') ?></a></li>
                </ul>
                <div id='tab_overview'>
                    <?php include(dirname(__FILE__) . '/overview.php'); ?>
                </div>
                <div id='tab_list'>
                    <?php include(dirname(__FILE__) . '/bible_list.php'); ?>
                </div>
                <div id='tab_actions'>
                    <?php include(dirname(__FILE__) . '/actions.php'); ?>
                </div>

                <?php if(config('download.enable')): ?>
                    <div id='tab_downloads'>
                        <?php include(dirname(__FILE__) . '/download.php'); ?>
                    </div>
                <?php endif; ?>

                <div id='tab_tos'>
                    <?php include(dirname(__FILE__) . '/tos.php'); ?>
                </div>
                <div id='tab_privacy'>
                    <?php include(dirname(__FILE__) . '/privacy.php'); ?>
                </div>
            </div>
        </div>
    </body>
</html>
