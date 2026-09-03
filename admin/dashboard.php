<?php

session_start();


// Login Protection
if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;
}


// Admin Role Protection
if ($_SESSION["role"] !== "admin") {

    echo "Access denied.";
    exit;
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">

</head>

<body>

    <h1>CampusHub</h1>

    <h2>Admin Dashboard</h2>


    <p>
        Welcome,
        <?php echo htmlspecialchars($_SESSION["name"]); ?>
    </p>


    <p>
        Email:
        <?php echo htmlspecialchars($_SESSION["email"]); ?>
    </p>


    <p>
        Role:
        <?php echo htmlspecialchars($_SESSION["role"]); ?>
    </p>


    <br>


    <h3>Admin Menu</h3>


    <a href="notices.php">
        Manage Notices
    </a>


    <br><br>


    <a href="../auth/logout.php">
        Logout
    </a>


</body>

</html>