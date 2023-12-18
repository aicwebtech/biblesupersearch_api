<?php
    $u = url('');
?>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta contentType="text/html; charset=UTF-8"/>
        <link rel="stylesheet" href="<?php echo $u ?>/css/login.css">
        <link rel="stylesheet" href="<?php echo $u ?>/js/bin/jquery-ui/jquery-ui.css">
        <link rel="stylesheet" href="<?php echo $u ?>/js/bin/jquery-ui/jquery-ui.theme.css">
        <script src='<?php echo $u ?>/js/bin/jquery/jquery-3.1.1.min.js'></script>
        <script src='<?php echo $u ?>/js/bin/jquery-ui/jquery-ui.js'></script>
        <script>
            $( function() {
                $( ".button" ).button();
            });
        </script>
    </head>

    <body>
        <div id="app">
            <div class='container'>
                <div class='content'>
                    <form action='<?php echo $u ?>/auth/login' method='POST'>
                        <input name='username' class='text' placeholder='Username' required /><br />
                        <input name='password' type='password' class='text' placeholder='Password' required /><br />
                        <input type='submit' value='Log In' class='button' /><br /><br />
                        <?php echo csrf_field() ?>
                        <a href='<?php echo route('password.request') ?>'>Reset Password</a>
                    </form>
                    <?php require( dirname(__FILE__) . '/../errors/form.php'); ?>
                </div>
            </div>
        </div>
    </body>
</html>
