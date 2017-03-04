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
            
            #container {
                margin:40px;
                border-radius: 30px;
                padding: 20px;
                background-color: #EEEEEE;
            }
            
            body {
                margin: 0;
                background-color: darkcyan;
            }
            
            #tabs {
                min-height: 500px;
            }
        </style>
    </head>
    <?php
        $http = (array_key_exists('HTTPS', $_SERVER) && !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
    ?>
    
    <body>
        <div id='container'>            
            <h1><?php echo trans('app.name') ?> <?php echo trans('app.documentation') ?></h1>
            <h2><?php echo trans('app.version') ?> <?php echo config('app.version'); ?></h2>
            <h2 class='warning'><?php echo trans('app.env_warnings.' . config('app.env')) ?></h2>

            <div id='tabs'>
                <ul>
                    <li><a href='#tab_query'><?php echo trans('query.name') ?></a></li>
                    <li><a href='#tab_statics'><?php echo trans('api.statics.name') ?></a></li>
                    <li><a href='#tab_bibles'><?php echo trans('api.bibles.name') ?></a></li>
                    <li><a href='#tab_books'><?php echo trans('api.books.name') ?></a></li>
                </ul>
                <div id='tab_query'>
                    <?php echo trans('api.action') ?>: <?php echo trans('query.name') ?><br />
                    URL: <?php echo $http . $_SERVER['SERVER_NAME'] ?>/api/
                </div>
                <div id='tab_statics'>
                    <?php echo trans('api.action') ?>: <?php echo trans('api.statics.name') ?><br />
                    URL: <?php echo $http . $_SERVER['SERVER_NAME'] ?>/api/statics
                </div>
                <div id='tab_bibles'>
                    <?php echo trans('api.action') ?>: <?php echo trans('api.bibles.name') ?><br />
                    URL: <?php echo $http . $_SERVER['SERVER_NAME'] ?>/api/bibles
                </div>
                <div id='tab_books'>
                    <?php echo trans('api.action') ?>: <?php echo trans('api.books.name') ?><br />
                    URL: <?php echo $http . $_SERVER['SERVER_NAME'] ?>/api/books
                </div>
            </div>
        </div>
    </body>
</html>
