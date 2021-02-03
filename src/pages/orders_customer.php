<html>
<head>
    <link rel="icon" href="../resources/bypass.jpg"/>
    <link rel="stylesheet" href="styles.css">
    <title>Twoje zamówienia - Burger Familia</title>
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
    <h1> Twoje zamówienia </h1>
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
$new_order = $_SESSION['NEW'];

if ($new_order == 1) {
    $_SESSION['NEW'] = 0;
    echo "
        <div style=\"color: forestgreen; font-weight: bold; border: 5px double limegreen;\">
            <p>Zamówienie złożone pomyślnie!<br>Śledź jego status tutaj!</p>
        </div>
    ";
}

if (!$is_customer) {
    echo "Jak się tu dostałeś?<br> Powrót do <a href='home.php'>strony tytułowej</a>.";
}
else {

    $query = "SELECT DISTINCT
               O.id,
               O.ordered_date,
               O.estimated_arrival,
               O.arrived_at,
               A.postal_code,
               A.town,
               A.street,
               A.num
        FROM \"Order\" O
            JOIN OrderEntries OE ON O.id = OE.\"order\"
            JOIN Entry E ON OE.entry = E.id
            JOIN Address A ON O.address = A.id
        WHERE E.customer = $id
        ORDER BY O.ordered_date DESC";

    $order_stmt = oci_parse($conn, $query);
    $ret = oci_execute($order_stmt);

    $size_stmt = oci_parse($conn, "SELECT COUNT(id) AS CNT FROM ($query)");
    $ret2 = oci_execute($size_stmt);
    $counter = oci_fetch_array($size_stmt)['CNT'];
    oci_free_statement($size_stmt);

    if ($counter == 0) {
        echo "<div style='text-align: center;'>
            <p><i>Nie zamawiałeś jeszcze niczego w naszej burgerowni...<br>Może się na coś skusisz? </i></p>
            <p><a href='menu.php'>Zobacz nasze menu</a></p>
        </div>";
    }
    else {
        if (!$ret) {
            echo "Error gathering orders".oci_error()['message'];
        }
        if (!$ret2) {
            echo "Error checking size of fetched orders".oci_error()['message'];
        }

        while (($order_row = oci_fetch_array($order_stmt))) {

            $stmt = oci_parse($conn, "BEGIN order_status(:order, :status); END;");
            oci_bind_by_name($stmt, ':order', $order_row['ID']);
            oci_bind_by_name($stmt, ':status', $status, 2);
            $ret = oci_execute($stmt);
            oci_free_statement($stmt);

            if (!$ret)
                echo "Error checking status of fetched order".oci_error()['message'];

            $border_color = "";

            if ($status == 0) // pending
                $border_color = "limegreen";
            else if ($status == 1) // arrived
                $border_color = "darkgray";
            else // cancelled by employee
                $border_color = "red";

            echo "
                <div style='border-radius: 5px;
                            border: 3px solid $border_color;
                            text-align: left;
                            margin: 20px auto;
                            padding: 10px;
                            width: 40%;'>
                <p><b>Numer zamówienia:</b> ".$order_row['ID']."</p>
                <p><b>Data złożenia:</b> ".$order_row['ORDERED_DATE']."</p>
            ";

            if ($status == 0) { // pending
                echo "
                    <p><b>Przewidywana dostawa:</b> ".$order_row['ESTIMATED_ARRIVAL']."</p>
                    <p><b>Status:</b> W trakcie</p>
                ";
            }
            else if ($status == 1) { // arrived
                echo "
                    <p><b>Dostarczono:</b> ".$order_row['ARRIVED_AT']."</p>
                    <p><b>Status:</b> Dostarczono</p>
                ";
            }
            else { // cancelled by employee
                echo "<p><b>Status:</b> Anulowane</p>";
            }
            echo "
                <br>
                <p><b>Adres:</b><br>".$order_row['STREET']." ".$order_row['NUM']."<br>".
                $order_row['POSTAL_CODE']." ".$order_row['TOWN']."</p><br>
            ";

            $entry_query = "
                SELECT E.amount,
                       D.name,
                       D.price
                FROM OrderEntries OE
                    JOIN Entry E ON OE.entry = E.id
                    JOIN Dish D ON E.dish = D.id
                WHERE OE.\"order\" = {$order_row['ID']}
                ORDER BY D.name
            ";

            $entry_stmt = oci_parse($conn, $entry_query);
            $ret = oci_execute($entry_stmt);

            if (!$ret)
                echo "Error getting entries for this order";

            echo "<p><b>Zamówienie:</b>";
            while (($entry_row = oci_fetch_array($entry_stmt))) {
                $multiprice = $entry_row['PRICE'] * $entry_row['AMOUNT'];

                echo "<p style='text-align: right;'>"
                        .$entry_row['NAME'].", sztuk ".$entry_row['AMOUNT'].
                    " = $multiprice zł</p>";
            }

            oci_free_statement($entry_stmt);

            $stmt = oci_parse($conn, "BEGIN get_order_price(:order, :price); END;");
            oci_bind_by_name($stmt, ':order', $order_row['ID']);
            oci_bind_by_name($stmt, ':price', $price, 38);
            $ret = oci_execute($stmt);

            if (!$ret)
                echo "Error getting order price".oci_error()['message'];

            oci_free_statement($stmt);

            echo "
                <p style='text-align: right;'>dowóz = 10 zł</p>
                <hr>
                <p style='text-align: right;'><b>Cena sumarycznie: $price zł</b>
            </div>
            ";
        }

        oci_free_statement($order_stmt);
    }
}
?>
</body>
</html>