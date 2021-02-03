<?php
session_start();
session_unset();
header("Location: home.php", true, 301);
exit();
?>