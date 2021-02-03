<html>
<head>
    <link rel="icon" href="../resources/bypass.jpg"/>
    <link rel="stylesheet" href="styles.css">
    <title>Twój koszyk - Burger Familia</title>
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
    <h1> Twój koszyk </h1>
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

    $query = "
        SELECT D.id, D.img, D.name, E.amount, D.price
        FROM Entry E JOIN Dish D ON E.dish = D.id
        WHERE E.customer = $id AND E.flgInCart = 1
        ORDER BY D.name
    ";

    $stmt = oci_parse($conn, $query);
    $ret = oci_execute($stmt);

    if (!$ret) {
        echo "Error fetching Entries data".oci_error()['message'];
    }

    $price_total = 0;
    $incorrect_amount = false;

    $size_stmt = oci_parse($conn, "SELECT COUNT(id) AS CNT FROM ($query)");
    $ret = oci_execute($size_stmt);
    $counter = oci_fetch_array($size_stmt)['CNT'];
    oci_free_statement($size_stmt);

    if (!$ret) {
        echo "Error checking size of fetched Entries".oci_error()['message'];
    }

    if ($counter == 0) {
        echo "<div style='text-align: center;'>
            <p><i>Trochę tu pusto... Może zamówisz coś na ząb? </i></p>
            <p><a href='menu.php'>Zobacz nasze menu</a></p>
        </div>";
    }
    else {
        echo "
            <table style='width: 70%;'>
                <tr>
                    <th>Zdjęcie</th>
                    <th>Danie</th>
                    <th>Ilość</th>
                    <th>Dostępność</th>
                    <th>Zmień ilość</th>
                    <th>Zatwierdź</th>
                    <th>Cena w sumie</th>
                    <th>Usuń pozycję</th>
                </tr>
        ";

        while (($row = oci_fetch_array($stmt))) {
            $price_total += $row['PRICE'] * $row['AMOUNT'];

            echo "
                <tr>
                    <td>
                        <img src=\"" . $row['IMG'] . "\" alt=\"brak zdjęcia\" style=\"width:100px;height:100px;\">
                    </td>
                    <td>" . $row['NAME'] . "</td>
                    <td>" . $row['AMOUNT'] . "</td>
            ";

            $possible = oci_parse($conn, "BEGIN get_possible_order(:dish, :how_many); END;");
            oci_bind_by_name($possible, ':dish', $row['ID']);
            oci_bind_by_name($possible, ':how_many', $how_many, 38);
            $ret = oci_execute($possible);

            if (!$ret) {
                echo "<td>couldn't get data about possible orders</td>";
            } else {
                echo "<td>$how_many</td>";
            }
            oci_free_statement($possible);

            if ($row['AMOUNT'] > $how_many)
                $incorrect_amount = true;

            echo "
                <form accept-charset=\"utf-8\" action=\"change_in_cart.php\" method=\"post\">
                    <td>
                        <input type=\"number\" name=\"amount\" value=\"".$row['AMOUNT']."\">
                    </td>
                    <td>
                        <input type=\"hidden\" name=\"possible\" value=\"$how_many\">
                        <input type=\"hidden\" name=\"form_id\" value=\"" . $row['ID'] . "\">
                        <input type=\"submit\" name=\"submit\" value='✔'>
                    </td>
                </form>
                <td>
                    <span style='color: lightgray;'>
                        " . $row['PRICE'] . " × " . $row['AMOUNT'] . " = </span>" . $row['PRICE'] * $row['AMOUNT'] . "
                </td>
                <td>
                    <form accept-charset=\"utf-8\" action=\"change_in_cart.php\" method=\"post\">
                            <input type=\"hidden\" name=\"amount\" value='0'>
                            <input type=\"hidden\" name=\"possible\" value=\"$how_many\">
                            <input type=\"hidden\" name=\"form_id\" value=\"" . $row['ID'] . "\">
                            <input type=\"submit\" name=\"submit\" value='❌'>
                    </form>
                </td>
            </tr>
        ";

        }

        echo "
            <tr>
                <td>Dowóz:</td>
                <td>10</td>
                <td></td>
                <td></td>
                <td></td>
                <td>
                    <b>SUMA:</b>
                </td>
                <td>
                    <b>";
        echo            $price_total + 10;
        echo "      </b>
                </td>
            </tr>
        </table>";

        $current_time = time();
        $current_hour = intval(date('H', $current_time), 10);

        if ($current_hour >= 22 || $current_hour < 10) {
            echo "
                <div style='display: block; float: right; margin-right: 40px; margin-top: 20px;'>
                    <i>Dzisiaj jesteśmy już zamknięci. <br>
                    Złóż zamówienie jutro!<br>
                    Jesteśmy otwarci od 10 do 22</i>
                </div>
            ";
        }
        else if ($incorrect_amount) {
            echo "
                <div style='display: block; float: right; margin-right: 40px; margin-top: 20px;'>
                    <i>Niestety, nie mogę sfinalizować Twojego zamówienia. <br>
                    Dostępność niektórych pozycji uległa zmianie.<br>
                    Proszę, uaktualnij swoje zamówienie.</i>
                </div>
            ";
        }
        else {
            echo "
            <div style='display: block; float: right; margin-right: 40px; margin-top: 20px;'>
                <form accept-charset=\"utf-8\" action=\"make_order.php\" method=\"post\">
                    <input type=\"submit\" name=\"submit\" value='Dokonaj zamówienia'>
                </form>
            </div>
            ";
        }


        oci_free_statement($stmt);
    }
}
?>
</body>
</html>
