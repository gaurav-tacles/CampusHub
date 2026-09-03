<?php

session_start();


// Login Protection
if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;
}


// Faculty Role Protection
if ($_SESSION["role"] !== "faculty") {

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

    <title>Faculty Dashboard - CampusHub</title>

    <link
        rel="stylesheet"
        href="../assets/style.css"
    >

</head>

<body>

    <h1>CampusHub</h1>

    <h2>Faculty Dashboard</h2>

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

    <h3>Faculty Menu</h3>

    <div class="dashboard-links">

        <a href="attendance.php">
            Mark Attendance
        </a>

        <a href="students.php">
            Students
        </a>

        <a href="#">
            Notices
        </a>

        <a href="../auth/logout.php">
            Logout
        </a>

    </div>

</body>

</html>