<html>
<head>
    <link rel="icon" href="../resources/hermes-rivera-gv_XRp4dUqM-unsplash.jpg"/>
    <title>Burger Familia</title>
    <meta charset="UTF-8">
</head>
<body>
    <?php
    session_start();
    // Verify if user is logged
    $login = $_SESSION['LOGIN'];
    $password = $_SESSION['PASSWORD'];

    $login_ver = strcmp($login, "");
    $auth = $login_ver != 0;

    $conn = oci_connect("rk418291", "burgery", "//labora.mimuw.edu.pl/LABS");
    if (!$conn)
        echo "Error connecting: ".oci_error()['message'];

    $user_id = -1;

    if ($auth) {
        $stmt = oci_parse($conn,"VARIABLE id INT; EXECUTE :id := get_user('$login');");
        oci_bind_by_name($stmt, ':id', $user_id, 32, OCI_B_INT);
        $ret = oci_execute($stmt);
        if (!$ret)
            echo "Error getting user_id: ".oci_error()['message'];
    }
    ?>
    <div style="float: right;">
        <?php
        if ($auth) {
            $stmt = oci_parse($conn, "SELECT * FROM \"User\" WHERE id = '$user_id'");
            $user_row = oci_fetch_array($stmt);

            $role = -1;

            $stmt = oci_parse($conn,"VARIABLE role_ INT; EXECUTE :role_ := role('$login');");
            oci_bind_by_name($stmt, ':role_', $role, 2, OCI_B_INT);
            $ret = oci_execute($stmt);
            if (!$ret)
                echo "Error getting user role: ".oci_error()['message'];

            $is_customer = $role == 0;
            $is_employee = $role == 1;

            if ($is_customer) {
                echo "
                <p>
                    <a href=\"customer.php?id='$user_id'\">
                        Przejdź do profilu, ".$user_row['NAME']." ".$user_row['SURNAME']."</a>
                </p>
                <p>
                    <a href=\"sign_out.php\">Wyloguj</a>
                </p>
                ";
            }
            else if ($is_employee) {
                echo "
                <p>
                    <a href=\"employee.php?id='$user_id'\">
                        Przejdź do profilu, ".$user_row['NAME']." ".$user_row['SURNAME']."</a>
                </p>
                <p>
                    <a href=\"sign_out.php\">Wyloguj</a>
                </p>
                ";
            }
        }
        else {
            echo '
            <p>
                <a href="sign_in.php">Zaloguj się</a><br/>
                <a href="sign_up.php">Rejestracja</a>
            </p>
            ';
        }
        ?>
    </div>

    <div style="text-align: center;">
        <h1> Witaj na stronie burgerowni <i><b style="color: darkred;">Burger Familia</b></i> </h1>
    </div>
    <div>
        <?php
        if (!$auth) {
            echo '
            <p>
                Zaloguj się by kontynować.
            </p>
            ';
        }
        ?>
    </div>
  </body>
</html>
