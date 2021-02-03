<?php
session_start();

$conn = oci_connect("rk418291", "burgery", "//labora.mimuw.edu.pl/LABS");

if (!$conn)
    echo "Error connecting: " . oci_error()['message'];

$id = $_SESSION['ID'];
$auth = is_numeric($id);

$order = $_POST['order'];

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
    if (strcmp($order, "") != 0) {
        $stmt = oci_parse($conn, "BEGIN order_status(:order, :status); END;");
        oci_bind_by_name($stmt, ':order', $order);
        oci_bind_by_name($stmt, ':status', $status, 2);
        $ret = oci_execute($stmt);
        oci_free_statement($stmt);

        if ($ret && $status == 0) { // pending

            $stmt = oci_parse($conn, "
                UPDATE \"Order\" SET
                    flgActive = 0,
                    arrived_at = SYSTIMESTAMP
                WHERE id = :ord"
            );
            oci_bind_by_name($stmt, ':ord', $order);

            $ret = oci_execute($stmt);
            oci_free_statement($stmt);

            if ($ret) {
                $_SESSION['ORDER_DELIVERED'] = $order;
            }
        }
    }

    header("Location: orders_employee.php", true, 301);
    exit();
} else {
    header("Location: home.php", true, 301);
    exit();
}
