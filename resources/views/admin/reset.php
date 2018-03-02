<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta contentType="text/html; charset=UTF-8"/>
        <link rel="stylesheet" href="/css/login.css">
        <link rel="stylesheet" href="/js/bin/jquery-ui/jquery-ui.css">
        <link rel="stylesheet" href="/js/bin/jquery-ui/jquery-ui.theme.css">
        <script src='/js/bin/jquery/jquery-3.1.1.min.js'></script>
        <script src='/js/bin/jquery-ui/jquery-ui.js'></script>
        <script>
            $( function() {
                $( ".button" ).button();
            });
        </script>
    </head>

    <body>
        <div class='container'>
            <div class='content'>
                <form action='/auth/reset' method='POST'>
                    <input name='password' type='password' class='text' placeholder='Password'/><br />
                    <input type='submit' value='Log In' class='button' />
                    <?php echo csrf_field() ?>
                </form>
                <?php require( dirname(__FILE__) . '/../errors/form.php'); ?>
            </div>
        </div>
    </body>
</html>
