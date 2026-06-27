<?php
// e:\dmsk 29-4-26\logout.php
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
