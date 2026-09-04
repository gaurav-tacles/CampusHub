<?php
session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    echo "Access denied.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Results - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">

</head>

<body>

<h1>CampusHub</h1>

<h2>Manage Student Results</h2>

<p>
    Welcome,
    <?php echo htmlspecialchars($_SESSION["name"]); ?>
</p>

<p>
    Admin can add and manage student results from this page.
</p>

<br>

<a href="dashboard.php">Back to Dashboard</a>

</body>

</html>