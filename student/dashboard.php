<?php

session_start();

if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;

}

if ($_SESSION["role"] !== "student") {

    echo "Access denied.";
    exit;

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <h1>CampusHub</h1>
    <h5>Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?> </h5>
    <h2>Student Dashboard</h2>
    <p>Email : <?php echo htmlspecialchars($_SESSION["email"]); ?> </p>
    <p>Role : <?php echo htmlspecialchars($_SESSION["role"]); ?> </p>

    
    <a href="../auth/logout.php">Logout</a>

</body>
</html>