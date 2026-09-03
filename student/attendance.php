<?php

session_start();

require_once "../config/db.php";


// Login Protection
if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;
}


// Student Role Protection
if ($_SESSION["role"] !== "student") {

    echo "Access denied.";
    exit;
}


$userId = $_SESSION["user_id"];

// Get attendance summary
$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total_classes,
        SUM(status = 'present') AS present_classes,
        SUM(status = 'absent') AS absent_classes
     FROM attendance
     WHERE student_id = ?"
);

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$summary = $stmt->get_result()->fetch_assoc();

$totalClasses = $summary["total_classes"];
$presentClasses = $summary["present_classes"];
$absentClasses = $summary["absent_classes"];

if ($totalClasses > 0) {

    $attendancePercentage =
        ($presentClasses / $totalClasses) * 100;

} else {

    $attendancePercentage = 0;
}

if ($attendancePercentage >= 75) {

    $attendanceStatus = "Good";

} else {

    $attendanceStatus = "Low";
}


// Get student's attendance
$stmt = $conn->prepare(
    "SELECT attendance_date, status
     FROM attendance
     WHERE student_id = ?
     ORDER BY attendance_date DESC"
);

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Attendance - CampusHub</title>

    <link
        rel="stylesheet"
        href="../assets/style.css"
    >

</head>

<body>

    <h1>CampusHub</h1>

    <h2>My Attendance</h2>

    <div class="attendance-summary">

    <p>
        <strong>Total Classes:</strong>
        <?php echo $totalClasses; ?>
    </p>

    <p>
        <strong>Present:</strong>
        <?php echo $presentClasses; ?>
    </p>

    <p>
        <strong>Absent:</strong>
        <?php echo $absentClasses; ?>
    </p>

    <p>
        <strong>Attendance:</strong>
        <?php echo number_format($attendancePercentage, 2); ?>%
    </p>

    <p>
        <strong>Attendance Status:</strong>
        <?php echo htmlspecialchars($attendanceStatus); ?>
    </p>

</div>

    <table border="1" cellpadding="10">

        <tr>
            <th>Date</th>
            <th>Status</th>
        </tr>

        <?php if ($result->num_rows > 0): ?>

            <?php while ($attendance = $result->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?php
                        echo date(
                            "d-m-Y",
                            strtotime($attendance["attendance_date"])
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            ucfirst($attendance["status"])
                        );
                        ?>
                    </td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>

                <td colspan="2">
                    No attendance records found.
                </td>

            </tr>

        <?php endif; ?>

    </table>

    <br>

    <a href="dashboard.php">
        Back to Dashboard
    </a>

</body>

</html>