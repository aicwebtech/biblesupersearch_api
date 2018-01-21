<!DOCTYPE html>
<html>
    <head>
        <title><?php echo trans('app.name') ?> <?php echo config('app.version'); ?></title>
        <link rel="stylesheet" href="/js/bin/jquery-ui/jquery-ui.css">
        <link rel="stylesheet" href="/js/bin/jquery-ui/jquery-ui.theme.css">
        <script src='/js/bin/jquery/jquery-3.1.1.min.js'></script>
        <script src='/js/bin/jquery-ui/jquery-ui.js'></script>
        <script>
            $( function() {
                $( "#tabs" ).tabs();
            });
        </script>
        <style>
            .warning {
                color: red;
                background-color: #FAF834;
                padding: 5px;
                border-radius: 10px;
            }

            .hcenter {
                text-align: center;
                font-family: Verdana, Arial, sans-serif;
            }

            #container {
                margin:40px 80px;
                border-radius: 30px;
                padding: 20px;
                background-color: #EEEEEE;
            }

            body {
                margin: 0;
                background-color: #3c729b;
            }

            #tabs {
                min-height: 500px;
            }

            .parameters {
                border: 0;
                border-right: 1px solid black;
                border-bottom: 1px solid black;
                width: 100%;
            }

            pre code {
                display: inline-block;
                background-color: #EBC19B;
                padding: 10px;
            }

            .parameters tr th, .parameters tr td {
                border-left: 1px solid black;
                border-top: 1px solid black;
                border-right: 0;
                border-bottom: 0;
                padding: 3px;
            }

            .parameters th {
                background: #dddddd; /* For browsers that do not support gradients */
                background: -webkit-linear-gradient(#5aa7e2, #3c729b); /* For Safari 5.1 to 6.0 */
                background: -o-linear-gradient(#5aa7e2, #3c729b); /* For Opera 11.1 to 12.0 */
                background: -moz-linear-gradient(#5aa7e2, #3c729b); /* For Firefox 3.6 to 15 */
                background: linear-gradient(#5aa7e2,#3c729b); /* Standard syntax */
                padding: 3px;
                color: #eee;
            }

            @media print {
                #container {
                    margin: 0px;
                    padding: 0px;
                }
            }
        </style>
    </head>
    <?php
        $http = (array_key_exists('HTTPS', $_SERVER) && !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
        require_once( dirname(__FILE__) . '/functions.php');
    ?>
    <body>
        <div id='container'>
            <h1 class='hcenter'><?php echo trans('app.name') ?> <?php echo trans('app.documentation') ?></h1>
            <h2 class='hcenter'><?php echo trans('app.version') ?> <?php echo config('app.version'); ?></h2>
            <?php if(config('app.env') != 'production'): ?>
                <h2 class='hcenter warning'><?php echo trans('app.env_warnings.' . config('app.env')) ?></h2>
            <?php endif; ?>

            <div id='tabs'>
                <ul>
                    <li><a href='#tab_overview'><?php echo trans('api.overview.name') ?></a></li>
                    <li><a href='#tab_list'><?php echo trans('api.bible_list') ?></a></li>
                    <li><a href='#tab_query'><?php echo trans('api.action') . ': ' . trans('query.name') ?></a></li>
                    <li><a href='#tab_statics'><?php echo trans('api.action') . ': ' . trans('api.statics.name') ?></a></li>
                    <li><a href='#tab_bibles'><?php echo trans('api.action') . ': ' . trans('api.bibles.name') ?></a></li>
                    <li><a href='#tab_books'><?php echo trans('api.action') . ': ' . trans('api.books.name') ?></a></li>
                    <li><a href='#tab_version'><?php echo trans('api.action') . ': ' . trans('api.version.name') ?></a></li>
                    <li><a href='#tab_tos'><?php echo trans('api.tos') ?></a></li>
                    <li><a href='#tab_privacy'><?php echo trans('api.privacy') ?></a></li>
                </ul>
                <div id='tab_overview'>
                    <?php include(dirname(__FILE__) . '/overview.php'); ?>
                </div>
                <div id='tab_list'>
                    <?php include(dirname(__FILE__) . '/bible_list.php'); ?>
                </div>
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
                <div id='tab_version'>
                    <?php include(dirname(__FILE__) . '/version.php'); ?>
                </div>
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
