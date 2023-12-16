<?php 
    $meta_desc  = '';
    $meta_desc .= 'Free Bible Web Service API with documentation. Bible search and passage look up.';

    if(config('download.enable') && config('download.tab_enable')) {
        $meta_desc .= ' Bible Downloads: PDF, MySQL and more!';
    }

    $meta_desc .= ' Free and Open Source.';
    $meta_desc .= strlen($meta_desc);

    $u = url('');
?>

<!DOCTYPE html>
<html>
    <head>
        <title><?php echo config('app.name') ?></title>
        
        <?php if(config('download.enable')): ?>
            <link rel="stylesheet" href="<?php echo $u ?>/widgets/download/download.css">
        <?php endif; ?>        

        <meta charset="utf-8" />
        <meta name="description" content="<?php echo $meta_desc ?>" />
        <link rel="stylesheet" href="<?php echo $u ?>/js/bin/jquery-ui/jquery-ui.css">
        <link rel="stylesheet" href="<?php echo $u ?>/js/bin/jquery-ui/jquery-ui.theme.css">
        <link rel="stylesheet" href="<?php echo $u ?>/css/docs.css">
        <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $u ?>/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $u ?>/favicon-16x16.png">
        <script src='<?php echo $u ?>/js/bin/jquery/jquery-3.1.1.min.js'></script>
        <script src='<?php echo $u ?>/js/bin/jquery-ui/jquery-ui.js'></script>
        <script src='<?php echo $u ?>/js/docs.js'></script>
        
        <?php if(config('download.enable')): ?>
            <script src='<?php echo $u ?>/widgets/download/download.js'></script>
        <?php endif; ?>

    </head>
    <?php
        $http = (array_key_exists('HTTPS', $_SERVER) && !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
        require_once( dirname(__FILE__) . '/functions.php');
        $my_url = getServerUrl();
    ?>
    <body>
        <div id='container'>
            <h1 class='hcenter'><?php echo config('app.name') ?> <?php echo trans('app.documentation') ?></h1>
            <h6 class='hcenter'>Version <?php echo config('app.version'); ?></h6>
            <?php if(config('app.env') != 'production'): ?>
                <h2 class='hcenter warning'><?php echo trans('app.env_warnings.' . config('app.env')) ?></h2>
            <?php endif; ?>

            <div id='tabs'>
                <ul>
                    <li><a href='#tab_overview'><?php echo trans('api.overview.name') ?></a></li>
                    <li><a href='#tab_list'><?php echo trans('api.bible_list') ?></a></li>
                    <li><a href='#tab_actions'><?php echo trans('api.usage') ?></a></li>

                    <?php if(config('download.enable') && config('download.tab_enable')): ?>
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

                <?php if(config('download.enable') && config('download.tab_enable')): ?>
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

        <div id='footer'>
            <b><?php echo config('app.name') ?>&nbsp; &nbsp;Version <?php echo config('app.version'); ?></b><br /><br />
            This API is Free and Open Source software, licenced under the GNU GPL v3.0.<br /><br />
            To learn how to install it in it's entirety on your website, please visit:<br /><br />
            <a class='footer-link' href='http://www.biblesupersearch.com/downloads' target='_NEW'>http://www.BibleSuperSearch.com</a>
            <br /><br />

            Built and maintiained by 
            <a class='footer-link' href='http://www.aicwebtech.com/' target='_NEW'>AIC Web Tech</a>:
            <a class='footer-link' href='http://www.aicwebtech.com/' target='_NEW'>aicwebtech.com</a>
        </div>
    </body>
</html>
