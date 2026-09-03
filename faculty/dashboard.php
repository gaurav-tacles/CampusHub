<?php

session_start();

if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;

}

if ($_SESSION["role"] !== "faculty") {

    header("Location: ../auth/login.php");
    exit;

}

?>