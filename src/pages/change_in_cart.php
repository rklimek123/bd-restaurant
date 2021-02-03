<?php
session_start();

$conn = oci_connect("rk418291", "burgery", "//labora.mimuw.edu.pl/LABS");

if (!$conn)
    echo "Error connecting: ".oci_error()['message'];

$id = $_SESSION['ID'];
$auth = is_numeric($id);

$amount = $_POST['amount'];
$dish = $_POST['form_id'];
$possible = $_POST['possible'];

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

if ($is_customer) {
    if (strcmp($amount, "") != 0 &&
        strcmp($dish, "") != 0 &&
        strcmp($possible, "") != 0 &&
        $amount <= $possible) {

        $stmt = oci_parse($conn, "BEGIN update_entry(:customer, :dish, :amount); END;");
        oci_bind_by_name($stmt, ':customer', $id);
        oci_bind_by_name($stmt, ':dish', $dish);
        oci_bind_by_name($stmt, ':amount', $amount);
        $ret = oci_execute($stmt);
        oci_free_statement($stmt);

        if (!$ret) {
            $_SESSION['INVALID'] = 1;
        }
    }
    else {
        $_SESSION['INVALID'] = 1;
    }

    header("Location: cart.php", true, 301);
    exit();
}
else {
    header("Location: home.php", true, 301);
    exit();
}
?>