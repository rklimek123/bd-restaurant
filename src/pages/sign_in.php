<html>
<head>
    <link rel="icon" href="../resources/bypass.jpg"/>
    <title>Logowanie - Burger Familia</title>
    <meta charset="UTF-8">
</head>
<body>
<?php
session_start();

$conn = oci_connect("rk418291", "burgery", "//labora.mimuw.edu.pl/LABS");

if (!$conn)
    echo "Error connecting: ".oci_error()['message'];

$id = $_SESSION['ID'];
$auth = is_numeric($id);

$invalid_input = false;

// Verify, if the user has tried to sign in using this page
if (!$auth) {
    $log = $_POST['login'];
    $pass = $_POST['password'];

    if (strcmp($log, "") != 0 && strcmp($pass, "") != 0) {
        $stmt = oci_parse($conn, "SELECT * FROM \"User\" WHERE login = :login AND flgDeleted = 0");
        oci_bind_by_name($stmt, ":login", $log);
        oci_execute($stmt);
        $user_row = oci_fetch_array($stmt);

        if ($user_row) {
            $got_pass_hash = $user_row['PASSWORD'];

            if (password_verify($pass, $got_pass_hash)) {
                $_SESSION['ID'] = $user_row['ID'];
                $id = $user_row['ID'];
                $auth = true;
            }
        }

        oci_free_statement($stmt);

        if (!$auth)
            $invalid_input = true;
    }
}
?>
<div style="text-align: center;">
    <h1>Logowanie</h1>
</div>
<div>
<?php
if ($auth) {
    header("Location: home.php");
    exit();
}
else {
    echo "
        <div style='margin: auto; text-align: center; display: block;'>
    ";

    if ($invalid_input) {
        echo "
            <div style=\"color: red; font-weight: bold; border: 5px double darkred;\">
                <p>Podano niepoprawny login lub hasło</p>
            </div>
        ";
    }

    echo "
            <form accept-charset=\"utf-8\" action=\"sign_in.php\" method=\"post\"
                style='display: inline-block; margin: auto; padding: 30px; text-align: right;'>
                <label for=\"login\">Login</label>
                <input type=\"text\" name=\"login\"> <br>
                
                <label for=\"password\">Hasło</label>
                <input type=\"password\" name=\"password\"> <br> <br>
                
                <input type=\"submit\" name=\"submit\" value=\"Zaloguj się\">
            </form>
        </div>
    ";
}
?>
<p><a href="home.php">Powrót na stronę tytułową</a></p>
</div>
</body>
</html>
