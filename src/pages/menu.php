<html>
<head>
    <link rel="icon" href="../resources/bypass.jpg"/>
    <link rel="stylesheet" href="styles.css">
    <title>Menu - Burger Familia</title>
    <meta charset="UTF-8">
</head>
<body>
<?php
session_start();
// Verify if user is logged
$id = $_SESSION['ID'];
$auth = is_numeric($id);

$conn = oci_connect("rk418291", "burgery", "//labora.mimuw.edu.pl/LABS");
if (!$conn)
    echo "Error connecting: ".oci_error()['message'];

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
    <h1> Menu </h1>
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
$invalid_input = $_SESSION['INVALID'];

if ($invalid_input == 1) {
    $_SESSION['INVALID'] = 0;
    echo "
        <div style=\"color: red; font-weight: bold; border: 5px double darkred;\">
            <p>Wystąpił błąd lub podano niepoprawną wartość.</p>
        </div>
    ";
}

if (!$is_customer) {
    echo "Jak się tu dostałeś?<br> Powrót do <a href='home.php'>strony tytułowej</a>.";
}
else {
    $stmt = oci_parse($conn, "SELECT * FROM Dish");
    $ret = oci_execute($stmt);

    if (!$ret) {
        echo "Error fetching Dishes data".oci_error()['message'];
    }

    echo "
            <table>
                <tr>
                    <th>Zdjęcie</th>
                    <th>Danie</th>
                    <th>Składniki</th>
                    <th>Cena</th>
                    <th>Dostępność</th>
                    <th>Dodaj do koszyka</th>
                    <th>Zatwierdź</th>
                </tr>
    ";

    while (($row = oci_fetch_array($stmt))) {
        echo "
                <tr>
                    <td>
                        <img src=\"".$row['IMG']."\" alt=\"brak zdjęcia\" style=\"width:150px;height:150px;\">
                    </td>
                    <td>".$row['NAME']."</td>
        ";

        $ingredients = oci_parse($conn,
            "SELECT ingredient FROM NeedIngredient WHERE dish = ".$row['ID']." Order by ingredient");

        $ret = oci_execute($ingredients);

        if (!$ret) {
            echo "<td>couldn't get data about ingredients</td>";
        }
        else {
            echo "<td>";
            while (($row_ing = oci_fetch_array($ingredients))) {
                echo $row_ing['INGREDIENT'].", ";
            }
            echo "</td>";
        }
        oci_free_statement($ingredients);

        echo "<td>".$row['PRICE']."</td>";

        $possible = oci_parse($conn, "BEGIN get_possible_order(:dish, :how_many); END;");
        oci_bind_by_name($possible, ':dish', $row['ID']);
        oci_bind_by_name($possible, ':how_many', $how_many, 38);
        $ret = oci_execute($possible);

        if (!$ret) {
            echo "<td>couldn't get data about possible orders</td>";
        }
        else {
            echo "<td>$how_many</td>";
        }
        oci_free_statement($possible);

        echo "
                <form accept-charset=\"utf-8\" action=\"add_to_cart.php\" method=\"post\">
                    <td>
                        <input type=\"number\" name=\"amount\"> <br>
                    </td>
                    <td>
                        <input type=\"hidden\" name=\"possible\" value=\"$how_many\">
                        <input type=\"hidden\" name=\"form_id\" value=\"".$row['ID']."\">
                        <input type=\"submit\" name=\"submit\" value='✔'>
                    </td>
                </form>
            </tr>
        ";
    }
    echo "</table>";
    oci_free_statement($stmt);
}
?>
</body>
</html>
