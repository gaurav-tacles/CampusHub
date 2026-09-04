<?php
session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION["role"] !== "faculty") {
    echo "Access denied.";
    exit;
}

$facultyId = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT
        fs.id,
        fs.semester,
        s.subject_code,
        s.subject_name
     FROM faculty_subjects fs
     INNER JOIN subjects s
        ON fs.subject_id = s.id
     WHERE fs.faculty_id = ?
     ORDER BY fs.semester, s.subject_name"
);

$stmt->bind_param("i", $facultyId);
$stmt->execute();

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Subjects - CampusHub</title>

<link rel="stylesheet" href="../assets/style.css">

</head>

<body>

<h1>CampusHub</h1>

<h2>My Assigned Subjects</h2>

<p>
    Faculty:
    <?php echo htmlspecialchars($_SESSION["name"]); ?>
</p>

<br>

<?php if ($result->num_rows === 0): ?>

    <p>
        No subjects have been assigned to you yet.
    </p>

<?php else: ?>

    <table border="1" cellpadding="8">

        <tr>
            <th>Semester</th>
            <th>Subject Code</th>
            <th>Subject</th>
        </tr>

        <?php while ($subject = $result->fetch_assoc()): ?>

            <tr>

                <td>
                    Semester
                    <?php echo htmlspecialchars($subject["semester"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($subject["subject_code"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($subject["subject_name"]); ?>
                </td>

            </tr>

        <?php endwhile; ?>

    </table>

<?php endif; ?>

<br>

<a href="dashboard.php">Back to Dashboard</a>

</body>

</html>

<?php
$stmt->close();
?>