<html>
<head>
    <link rel="icon" href="../resources/bypass.jpg"/>
    <link rel="stylesheet" href="styles.css">
    <title>Magazyn - Burger Familia</title>
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
?>
<!-- Top-right corner panel -->
<div style="text-align: right;">
    <?php
    if ($auth) {
        $stmt = oci_parse($conn, "SELECT * FROM \"User\" WHERE id = $id");
        oci_execute($stmt);
        $user_row = oci_fetch_array($stmt);
        oci_free_statement($stmt);

        if (!$user_row)
            echo "Error: authorised as a nonexistent user <br>";

        $role = -1;
        $stmt = oci_parse($conn,"BEGIN role(:id_, :role_); END;");

        $ret1 = oci_bind_by_name($stmt, ':id_', $id);
        $ret2 = oci_bind_by_name($stmt, ':role_', $role);
        $ret = oci_execute($stmt);
        oci_free_statement($stmt);

        if (!$ret)
            echo "Error getting user role: ".oci_error()['message'];

        $is_customer = $role == 0;
        $is_employee = $role == 1;

        if ($is_customer) {
            echo "
            <p>
                <a href=\"customer.php?id=$id\">
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
                <a href=\"employee.php?id=$id\">
                    Przejdź do profilu, ".$user_row['NAME']." ".$user_row['SURNAME']."</a>
            </p>
            <p>
                <a href=\"sign_out.php\">Wyloguj</a>
            </p>
            ";
        }
        else {
            echo "Error when got user role: ".oci_error()['message'];
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

<!-- Hello text -->
<div style="text-align: center;">
    <h1>Magazyn</h1>
</div>

<!-- Action ribbon -->
<div>
    <?php
    if (!$auth) {
        echo '
        <p>
            Zaloguj się by kontynować.
        </p>
        ';
    }
    else {

        echo "
            <a href='home.php'>Strona tytułowa</a> |
        ";

        if ($is_customer) {
            echo "
                <a href='menu.php'>Menu</a> | 
                <a href='cart.php'>Koszyk</a> | 
                <a href='orders_customer.php'>Zamówienia</a>
            ";
        }
        else if ($is_employee) {
            echo "
                <a href='storage.php'>Magazyn</a> | 
                <a href='orders_employee.php'>Zamówienia</a> | 
            ";
        }
    }
    ?>
</div>

<?php
if ($auth) {
    $stmt = oci_parse($conn, "SELECT * FROM \"User\" WHERE id = $id");
    oci_execute($stmt);
    $user_row = oci_fetch_array($stmt);
    oci_free_statement($stmt);

    if (!$user_row)
        echo "Error: authorised as a nonexistent user <br>";

    $role = -1;
    $stmt = oci_parse($conn,"BEGIN role(:id_, :role_); END;");

    $ret1 = oci_bind_by_name($stmt, ':id_', $id);
    $ret2 = oci_bind_by_name($stmt, ':role_', $role);
    $ret = oci_execute($stmt);
    oci_free_statement($stmt);

    if (!$ret)
        echo "Error getting user role: ".oci_error()['message'];

    $is_customer = $role == 0;
    $is_employee = $role == 1;

    if ($is_employee) {
        $amount = $_POST['amount'];
        $form_id = $_POST['form_id'];


        if ($amount > 0) {
            $stmt = oci_parse($conn, "SELECT name FROM Ingredient WHERE name = :name");
            oci_bind_by_name($stmt, ':name', $form_id);
            $ret = oci_execute($stmt, OCI_NO_AUTO_COMMIT);
            $row = oci_fetch_array($stmt);
            oci_free_statement($stmt);

            if (!$ret || !$row) {
                oci_rollback($conn);
            }
            else {
                $stmt = oci_parse($conn, "BEGIN stock_ingredient(:name, :amount); END;");
                oci_bind_by_name($stmt, ':name', $form_id);
                oci_bind_by_name($stmt, ':amount', $amount);
                $ret = oci_execute($stmt, OCI_NO_AUTO_COMMIT);
                oci_free_statement($stmt);

                if (!$ret) {
                    oci_rollback($conn);
                }
                else {
                    oci_commit($conn);
                    // maybe sleep?
                }
            }
        }

        echo "
            <table>
                <tr>
                    <th>Składnik</th>
                    <th>Stan na magazynie</th>
                    <th>Zaopatrz</th>
                    <th>Zatwierdź</th>
                </tr>
        ";

        $stmt = oci_parse($conn, "SELECT * FROM Ingredient ORDER BY name");
        oci_execute($stmt);

        while (($row = oci_fetch_array($stmt))) {
            echo "
                <tr>
                    <td>".$row['NAME']."</td>
                    <td>".$row['STOCK']."</td>
                    
                    <form accept-charset=\"utf-8\" action=\"storage.php\" method=\"post\">
                        <td>
                            <input type=\"number\" name=\"amount\"> <br>
                        </td>
                        <td>
                            <input type=\"hidden\" name=\"form_id\" value=\"".$row['NAME']."\">
                            <input type=\"submit\" name=\"submit\" value='✔'>
                        </td>  
                    </form>
                </tr>
            ";
        }

        oci_free_statement($stmt);

        echo "</table><br><a href='add_ingredient.php'><b>Dodaj składnik</b></a>";
    }
}

if (!$auth || !$is_employee)
    echo "Jak się du dostałeś?<br>";
?>
</body>
</html>
