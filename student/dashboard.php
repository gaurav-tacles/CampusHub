<?php

session_start();
require_once "../config/db.php";

$userId = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT
        users.name,
        users.email,
        student_profiles.student_id,
        student_profiles.course,
        student_profiles.semester
     FROM users
     LEFT JOIN student_profiles
        ON users.id = student_profiles.user_id
     WHERE users.id = ?"
);

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$result = $stmt->get_result();

$student = $result->fetch_assoc();

$stmt->close();

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

<h2>
    Welcome,
    <?php echo htmlspecialchars($student["name"]); ?>
</h2>

<p>
    <strong>Email:</strong>
    <?php echo htmlspecialchars($student["email"]); ?>
</p>

<p>
    <strong>Student ID:</strong>
    <?php echo htmlspecialchars($student["student_id"] ?? "Not completed"); ?>
</p>

<p>
    <strong>Course:</strong>
    <?php echo htmlspecialchars($student["course"] ?? "Not completed"); ?>
</p>

<p>
    <strong>Semester:</strong>
    <?php echo htmlspecialchars($student["semester"] ?? "Not completed"); ?>
</p>

<br>

<div class="dashboard-links">

    <a href="profile.php">
        My Profile
    </a>

    <a href="attendance.php">
        Attendance
    </a>
    

    <a href="results.php">
        Results
    </a>

    <a href="notices.php">
        Notices
    </a>
    
    <a href="assignments.php">
        Assignments
    </a>

    <a href="../auth/logout.php">
        Logout
    </a>

</div>

</body>
</html>