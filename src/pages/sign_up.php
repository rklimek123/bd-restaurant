<html>
<head>
    <link rel="icon" href="../resources/hermes-rivera-gv_XRp4dUqM-unsplash.jpg"/>
    <title>Rejestracja - Burger Familia</title>
    <meta charset="UTF-8">
</head>
<body>
<?php
session_start();

$login = $_SESSION['LOGIN'];
$password = $_SESSION['PASSWORD'];

$login_ver = strcmp($login, "");
$auth = $login_ver != 0;

$conn = oci_connect("rk418291", "burgery", "//labora.mimuw.edu.pl/LABS");
if (!$conn)
    echo "Error connecting: ".oci_error()['message'];
?>
<div style="text-align: center;">
    <h1>Rejestracja</h1>
</div>
<div>
    <?php
    if ($auth) {
        echo "
            <p>
                Zalogowano jako ".$user_row['NAME']." ".$user_row['SURNAME']." <br>
                Aby zarejestrować się jako nowy użytkownik, musisz się <a href='sign_out.php'>wylogować</a>
            </p>
        ";
    }
    else {
        echo "
            <div>
                <form action=\"sign_in.php\" method=\"post\">
                    <input type=\"text\" name=\"login\"> <br>
                    <label for=\"login\">Login</label>
                    
                    <input type=\"text\" name=\"password\"> <br>
                    <label for=\"password\">Hasło</label>
                    
                    <input type=\"submit\" name=\"submit\" value=\"Zaloguj się\">
                </form>
            </div>
            ";
    }
    ?>
</div>
</body>
</html>
