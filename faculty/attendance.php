<?php

session_start();

require_once "../config/db.php";


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

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $attendanceDate = date("Y-m-d");
    $statuses = $_POST["status"] ?? [];

    foreach ($statuses as $studentId => $status) {

        if ($status !== "present" && $status !== "absent") {
            continue;
        }

        $stmt = $conn->prepare(
            "INSERT INTO attendance
            (student_id, attendance_date, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status)"
        );

        $stmt->bind_param(
            "iss",
            $studentId,
            $attendanceDate,
            $status
        );

        $stmt->execute();
    }

    echo "Attendance saved successfully.";
}


        // Get all students
        $stmt = $conn->prepare(
            "SELECT u.id, u.name, u.email, sp.student_id
            FROM users u
            INNER JOIN student_profiles sp
                ON u.id = sp.user_id
            WHERE u.role = 'student'
            ORDER BY u.name"
        );

        $stmt->execute();

        $result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mark Attendance - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>

    <h1>CampusHub</h1>

    <h2>Mark Attendance</h2>

    <form method="POST">

        <p>
            Attendance Date:
            <strong><?php echo date("d-m-Y"); ?></strong>
        </p>

        <br><br>

        <table border="1" cellpadding="10">

            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
            </tr>

            <?php while ($student = $result->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?php echo htmlspecialchars($student["student_id"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($student["name"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($student["email"]); ?>
                    </td>

                    <td>

                        <label>
                            <input
                                type="radio"
                                name="status[<?php echo $student["id"]; ?>]"
                                value="present"
                                required
                            >
                            Present
                        </label>

                        <label>
                            <input
                                type="radio"
                                name="status[<?php echo $student["id"]; ?>]"
                                value="absent"
                            >
                            Absent
                        </label>

                    </td>

                </tr>

            <?php endwhile; ?>

        </table>

        <br>

        <button type="submit">
            Save Attendance
        </button>

    </form>

</body>

</html>