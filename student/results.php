<?php

session_start();

require_once "../config/db.php";

// Only students can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Load Published Results
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.subject_code,
        s.subject_name,
        fs.semester,
        r.marks,
        r.published_at
    FROM results r

    INNER JOIN faculty_subjects fs
        ON r.faculty_subject_id = fs.id

    INNER JOIN subjects s
        ON fs.subject_id = s.id

    WHERE r.student_id = ?
      AND r.status = 'published'

    ORDER BY fs.semester, s.subject_name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();

$results = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Results - CampusHub</title>

    <link
        rel="stylesheet"
        href="../assets/style.css"
    >

</head>

<body>

<div class="container">

    <h1>My Results</h1>

    <p>
        View your published examination results.
    </p>

    <?php if ($results->num_rows === 0): ?>

        <p>
            No published results are available yet.
        </p>

    <?php else: ?>

        <table
            border="1"
            cellpadding="10"
            cellspacing="0"
        >

            <thead>

                <tr>

                    <th>Semester</th>

                    <th>Subject Code</th>

                    <th>Subject Name</th>

                    <th>Marks</th>

                    <th>Published On</th>

                </tr>

            </thead>

            <tbody>

                <?php while ($result = $results->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($result["semester"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($result["subject_code"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($result["subject_name"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($result["marks"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($result["published_at"]) ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    <?php endif; ?>

    <br>

    <a href="dashboard.php">
        ← Back to Student Dashboard
    </a>

</div>

</body>

</html>