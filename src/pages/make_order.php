<?php
session_start();

$conn = oci_connect("rk418291", "burgery", "//labora.mimuw.edu.pl/LABS");

if (!$conn)
    echo "Error connecting: ".oci_error()['message'];

$id = $_SESSION['ID'];
$auth = is_numeric($id);

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

$current_time = time();
$current_hour = intval(date('H', $current_time), 10);

if ($is_customer) {
    if ($current_hour >= 22 || $current_hour < 10) {
        header("Location: cart.php", true, 301);
        exit();
    }
    else {
        $stmt = oci_parse($conn, "BEGIN place_order(:customer, :success); END;");
        oci_bind_by_name($stmt, ':customer', $id);
        oci_bind_by_name($stmt, ':success', $success);
        $ret = oci_execute($stmt);
        oci_free_statement($stmt);

        if (!$ret) {
            header("Location: home.php", true, 301);
            exit();
        }

        if ($success == -1) {
            header("Location: cart.php", true, 301);
            exit();
        }
        else if ($success == 0) {
            header("Location: menu.php", true, 301);
            exit();
        }
        else if ($success == 1) {
            $_SESSION['NEW'] = 1;
            header("Location: orders_customer.php", true, 301);
            exit();
        }
    }
}
else {
header("Location: home.php", true, 301);
exit();
}
?>