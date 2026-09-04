<?php

session_start();

require_once "../config/db.php";

// Only admin can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

$assignment_id = intval($_GET["assignment_id"] ?? 0);

if ($assignment_id <= 0) {
    die("Invalid assignment.");
}

/*
|--------------------------------------------------------------------------
| LOAD ASSIGNMENT
|--------------------------------------------------------------------------
*/

$assignment_sql = "
    SELECT
        a.id,
        a.title,
        a.description,
        a.due_date,

        fs.semester,

        s.subject_code,
        s.subject_name,

        u.name AS faculty_name

    FROM assignments a

    INNER JOIN faculty_subjects fs
        ON a.faculty_subject_id = fs.id

    INNER JOIN subjects s
        ON fs.subject_id = s.id

    INNER JOIN users u
        ON fs.faculty_id = u.id

    WHERE a.id = ?
";

$assignment_stmt = $conn->prepare($assignment_sql);

$assignment_stmt->bind_param(
    "i",
    $assignment_id
);

$assignment_stmt->execute();

$assignment_result = $assignment_stmt->get_result();

if ($assignment_result->num_rows === 0) {
    die("Assignment not found.");
}

$assignment = $assignment_result->fetch_assoc();

$assignment_stmt->close();

/*
|--------------------------------------------------------------------------
| LOAD ALL STUDENTS + THEIR SUBMISSION
|--------------------------------------------------------------------------
*/

$students_sql = "
    SELECT
        u.id AS student_id,
        u.name,
        u.email,

        sub.id AS submission_id,
        sub.file_name,
        sub.file_path,
        sub.submitted_at,
        sub.status,
        sub.marks,
        sub.faculty_feedback

    FROM users u

    INNER JOIN student_profiles sp
        ON u.id = sp.user_id

    LEFT JOIN assignment_submissions sub
        ON u.id = sub.student_id
        AND sub.assignment_id = ?

    WHERE u.role = 'student'
      AND sp.semester = ?

    ORDER BY u.name ASC
";

$students_stmt = $conn->prepare($students_sql);

$students_stmt->bind_param(
    "ii",
    $assignment_id,
    $assignment["semester"]
);

$students_stmt->execute();

$students_result = $students_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Assignment Submissions - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">

</head>

<body>

<div class="container">

    <h1>Assignment Submission Details</h1>


    <div class="assignment-card">

        <h2>
            <?php
            echo htmlspecialchars(
                $assignment["title"]
            );
            ?>
        </h2>

        <p>
            <strong>Subject:</strong>

            <?php
            echo htmlspecialchars(
                $assignment["subject_code"] .
                " - " .
                $assignment["subject_name"]
            );
            ?>
        </p>

        <p>
            <strong>Semester:</strong>

            <?php
            echo htmlspecialchars(
                $assignment["semester"]
            );
            ?>
        </p>

        <p>
            <strong>Faculty:</strong>

            <?php
            echo htmlspecialchars(
                $assignment["faculty_name"]
            );
            ?>
        </p>

        <p>
            <strong>Due Date:</strong>

            <?php
            echo htmlspecialchars(
                date(
                    "d M Y, h:i A",
                    strtotime(
                        $assignment["due_date"]
                    )
                )
            );
            ?>
        </p>

    </div>


    <?php if ($students_result->num_rows === 0): ?>

        <p>
            No students found for this semester.
        </p>

    <?php else: ?>

        <table border="1" cellpadding="10" cellspacing="0">

            <thead>

                <tr>

                    <th>Student</th>

                    <th>Email</th>

                    <th>Submission</th>

                    <th>Submitted At</th>

                    <th>Status</th>

                    <th>Marks</th>

                    <th>Faculty Feedback</th>

                </tr>

            </thead>

            <tbody>

                <?php while (
                    $student =
                    $students_result->fetch_assoc()
                ): ?>

                    <tr>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $student["name"]
                            );
                            ?>
                        </td>


                        <td>
                            <?php
                            echo htmlspecialchars(
                                $student["email"]
                            );
                            ?>
                        </td>


                        <td>

                            <?php if (
                                $student["submission_id"] !== null
                            ): ?>

                                <a
                                    href="../<?php
                                    echo htmlspecialchars(
                                        $student["file_path"]
                                    );
                                    ?>"
                                    target="_blank"
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        $student["file_name"]
                                    );
                                    ?>
                                </a>

                            <?php else: ?>

                                <span>
                                    Not submitted
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                $student["submitted_at"] !== null
                            ): ?>

                                <?php
                                echo htmlspecialchars(
                                    date(
                                        "d M Y, h:i A",
                                        strtotime(
                                            $student["submitted_at"]
                                        )
                                    )
                                );
                                ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                $student["submission_id"] === null
                            ): ?>

                                <strong>
                                    Not Submitted
                                </strong>

                            <?php elseif (
                                $student["status"] === "graded"
                            ): ?>

                                <strong>
                                    Graded
                                </strong>

                            <?php else: ?>

                                <strong>
                                    Submitted
                                </strong>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                $student["marks"] !== null
                            ): ?>

                                <?php
                                echo htmlspecialchars(
                                    $student["marks"]
                                );
                                ?>/30

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $student["faculty_feedback"]
                                )
                            ): ?>

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $student[
                                            "faculty_feedback"
                                        ]
                                    )
                                );
                                ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    <?php endif; ?>

</div>

</body>

</html>

<?php

$students_stmt->close();

?>