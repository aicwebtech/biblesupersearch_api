<html>
    <head>

    </head>

    <body>
        <form action='/auth/login' method='POST'>
            <table> 
                <tr>
                    <td>Username: </td>
                    <td><input name='username' /></td>
                </tr>   
                <tr>
                    <td>Password: </td>
                    <td><input name='password' type='password' /></td>
                </tr>   
            </table>
            <?php echo csrf_field() ?>
            <input type='submit' value='LOGIN OF FUN' />
        </form>
    </body>
</html>