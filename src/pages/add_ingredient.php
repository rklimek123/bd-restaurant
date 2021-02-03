<html>
<head>
    <link rel="icon" href="../resources/bypass.jpg"/>
    <title>Dodaj składnik - Burger Familia</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
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

if ($auth) {
    $stmt = oci_parse($conn, "SELECT * FROM \"User\" WHERE id = $id");
    oci_execute($stmt);
    $user_row = oci_fetch_array($stmt);
    oci_free_statement($stmt);

    if (!$user_row)
        echo "Error: authorised as a nonexistent user <br>";

    $role = -1;
    $stmt = oci_parse($conn, "BEGIN role(:id_, :role_); END;");

    $ret1 = oci_bind_by_name($stmt, ':id_', $id);
    $ret2 = oci_bind_by_name($stmt, ':role_', $role);
    $ret = oci_execute($stmt);
    oci_free_statement($stmt);

    if (!$ret)
        echo "Error getting user role: " . oci_error()['message'];

    $is_customer = $role == 0;
    $is_employee = $role == 1;

    if ($is_employee) {
        $name = $_POST['name'];
        $amount = $_POST['amount'];

        if (strcmp($name, "") != 0 && strcmp($amount, "") != 0) {
            if ($amount >= 0) {
                $stmt = oci_parse($conn, "SELECT name FROM Ingredient WHERE name = :name");
                oci_bind_by_name($stmt, ':name', $name);
                $ret = oci_execute($stmt, OCI_NO_AUTO_COMMIT);
                $row = oci_fetch_array($stmt);
                oci_free_statement($stmt);

                if (!$ret || $row) {
                    $invalid_input = true;
                }
                else {
                    $stmt = oci_parse($conn, "BEGIN stock_ingredient(:name, :amount); END;");
                    oci_bind_by_name($stmt, ':name', $name);
                    oci_bind_by_name($stmt, ':amount', $amount);
                    $ret = oci_execute($stmt, OCI_NO_AUTO_COMMIT);
                    oci_free_statement($stmt);

                    if (!$ret) {
                        oci_rollback($conn);
                        $invalid_input = true;
                    }
                    else {
                        oci_commit($conn);
                        header("Location: storage.php");
                        exit();
                    }
                }
            }
            else {
                $invalid_input = true;
            }
        }
        else if (strcmp($_POST['submit'], "") != 0) {
            $invalid_input = true;
        }
    }
}
?>

<!-- Hello text -->
<div style="text-align: center;">
    <h1>Dodaj składnik</h1>
    <i>Przekierowanie do magazynu oznacza sukces</i>
</div>

<?php
if ($is_employee) {
    echo "<div style='margin: auto; text-align: center; display: block;'>";

    if ($invalid_input) {
        echo "
            <div style=\"color: red; font-weight: bold; border: 5px double darkred;\">
                <p>Podano niepoprawne dane (np. Początkowa ilość mniejsza niż 0)</p>
                <p>lub składnik o podanej nazwie już istnieje.</p>
            </div>
        ";
    }

    echo "
            <form accept-charset=\"utf-8\" action=\"add_ingredient.php\" method=\"post\"
                style='display: inline-block; margin: auto; padding: 30px; text-align: right;'>
                <label for=\"name\">Nazwa</label>
                <input type=\"text\" name=\"name\"> <br>
                
                <label for=\"amount\">Początkowa ilość</label>
                <input type=\"number\" name=\"amount\"> <br> <br>
                
                <input type=\"submit\" name=\"submit\" value=\"Dodaj składnik\">
            </form>
    ";

    echo "</div><p>Powrót do <a href=\"storage.php\">magazynu</a>.</p>";
}

if (!$auth || !$is_employee)
    echo "Jak się tu dostałeś?<br>";
?>
<p>Powrót na <a href="home.php">stronę tytułową</a>.</p>
</body>
</html>
