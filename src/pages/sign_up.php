<html>
<head>
    <link rel="icon" href="../resources/hermes-rivera-gv_XRp4dUqM-unsplash.jpg"/>
    <title>Rejestracja - Burger Familia</title>
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
$created_user = false;

if (!$auth) {
    $login = $_POST['login'];
    $password = $_POST['password'];
    $password_repeat = $_POST['password_repeat'];
    $email = $_POST['email'];
    $name = $_POST['name'];
    $surname = $_POST['surname'];

    $town = $_POST['town'];
    $street = $_POST['street'];
    $num = $_POST['num'];
    $postal = $_POST['postal'];

    if ((strcmp($login, "") != 0) &&
        (strcmp($password, "") != 0) &&
        (strcmp($password_repeat, "") != 0) &&
        (strcmp($password, $password_repeat) == 0) &&
        (strcmp($name, "") != 0) &&
        (strcmp($surname, "") != 0) &&
        (strcmp($town, "") != 0) &&
        (strcmp($street, "") != 0) &&
        (strcmp($num, "") != 0) &&
        (strcmp($postal, "") != 0)
        ) {

        $address = -1;
        $stmt = oci_parse($conn, "BEGIN add_address(:postal_code,
                                                       :town,
                                                       :street,
                                                       :num,
                                                       :address); END;");
        oci_bind_by_name($stmt, ':postal_code', $postal);
        oci_bind_by_name($stmt, ':town', $town);
        oci_bind_by_name($stmt, ':street', $street);
        oci_bind_by_name($stmt, ':num', $num);
        oci_bind_by_name($stmt, ':address', $address, 38);

        $ret = oci_execute($stmt);
        oci_free_statement($stmt);

        if(!$ret) {
            echo "Error adding address: ".oci_error()['message'];
            $invalid_input = true;
        }
        else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $email_null = strcmp($email, "") == 0;

            if ($email_null) {
                $stmt = oci_parse($conn, "BEGIN sign_up(:login,
                                                       :password,
                                                       NULL,
                                                       :name,
                                                       :surname,
                                                       :address,
                                                       :success); END;");
            }
            else {
                $stmt = oci_parse($conn, "BEGIN sign_up(:login,
                                                       :password,
                                                       :email,
                                                       :name,
                                                       :surname,
                                                       :address,
                                                       :success); END;");
            }

            oci_bind_by_name($stmt, ':login', $login);
            oci_bind_by_name($stmt, ':password', $hashed_password);

            if (!$email_null)
                oci_bind_by_name($stmt, ':email', $email);

            $sign_up_success = -1;
            oci_bind_by_name($stmt, ':name', $name);
            oci_bind_by_name($stmt, ':surname', $surname);
            oci_bind_by_name($stmt, ':address', $address);
            oci_bind_by_name($stmt, ':success', $sign_up_success);

            $ret = oci_execute($stmt);
            oci_free_statement($stmt);

            if(!$ret)
                echo "Error adding user: ".oci_error()['message'];

            if ($ret && $sign_up_success == 0) {
                $created_user = true;
            }
            else {
                $invalid_input = true;
            }
        }
    }
    else if (strcmp($_POST['submit'], "") != 0) {
        $invalid_input = true;
    }
}

?>
<div style="text-align: center;">
    <h1>Rejestracja</h1>
</div>
<div>
<?php
if ($auth) {
    echo "
        <div style='text-align: center;'>
            <p>
                Aby stworzyć nowe konto klienta, musisz się
                <a href='sign_out.php'>wylogować</a>.
            </p>
        </div>    
    ";
}
else if (!$created_user) {
    echo "
        <div style='margin: auto; text-align: center; display: block;'>
        <p><i>Zarejestruj nowe konto klienta</i></p>
    ";

    if ($invalid_input) {
        echo "
            <div style=\"color: red; font-weight: bold; border: 5px double darkred;\">
                <p>Konto o podanym loginie już istnieje,</p>
                <p>nie wypełniłeś każdej komórki (tylko email może być pusty)</p>
                <p>lub podałeś inne hasło przy wpisaniu go za drugim razem. Spróbuj ponownie.</p>
            </div>
        ";
    }

    echo "
            <form accept-charset=\"utf-8\" action=\"sign_up.php\" method=\"post\"
                style='display: inline-block; margin: auto; padding: 30px; text-align: right;'>
                
                <label for=\"login\">Login</label>
                <input type=\"text\" name=\"login\"> <br>
                
                <label for=\"password\">Hasło</label>
                <input type=\"password\" name=\"password\"> <br>
                
                <label for=\"password_repeat\">Powtórz hasło</label>
                <input type=\"password\" name=\"password_repeat\"> <br>
                
                <label for=\"email\">Email</label>
                <input type=\"text\" name=\"email\"> <br>
                
                <br>
                
                <label for=\"name\">Imię</label>
                <input type=\"text\" name=\"name\"> <br>
                
                <label for=\"surname\">Nazwisko</label>
                <input type=\"text\" name=\"surname\"> <br>
                
                <br>
                
                <label for=\"town\">Miasto</label>
                <input type=\"text\" name=\"town\"> <br>
                
                <label for=\"street\">Ulica</label>
                <input type=\"text\" name=\"street\"> <br>
                
                <label for=\"num\">Numer domu (format: nr. budynku/nr. mieszkania dla mieszkań)</label>
                <input type=\"text\" name=\"num\"> <br>
                
                <label for=\"postal\">Kod pocztowy</label>
                <input type=\"text\" name=\"postal\"> <br>
                
                <br>
                
                <input type=\"submit\" name=\"submit\" value=\"Zarejestruj\">
            </form>
        </div>
    ";
}
else {
    echo "
        <p>
            Rejestracja przebiegła pomyślnie.<br>
            <a href='home.php'>Wróć na stronę tytułową</a>
            lub <a href='sign_in.php'>zaloguj się</a>.
        </p>
    ";
}
?>
</div>
</body>
</html>
