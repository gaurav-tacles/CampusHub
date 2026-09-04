<?php

session_start();

require_once "../config/db.php";

// Only admin can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD ALL ACADEMIC ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.title,
        a.description,
        a.due_date,
        a.created_at,

        fs.semester,

        s.subject_code,
        s.subject_name,

        u.name AS faculty_name,
        u.email AS faculty_email,

        COUNT(sub.id) AS total_submissions,

        SUM(
            CASE
                WHEN sub.status = 'graded'
                THEN 1
                ELSE 0
            END
        ) AS graded_submissions

    FROM assignments a

    INNER JOIN faculty_subjects fs
        ON a.faculty_subject_id = fs.id

    INNER JOIN subjects s
        ON fs.subject_id = s.id

    INNER JOIN users u
        ON fs.faculty_id = u.id

    LEFT JOIN assignment_submissions sub
        ON a.id = sub.assignment_id

    GROUP BY
        a.id,
        a.title,
        a.description,
        a.due_date,
        a.created_at,
        fs.semester,
        s.subject_code,
        s.subject_name,
        u.name,
        u.email

    ORDER BY a.due_date ASC
";

$stmt = $conn->prepare($sql);

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Academic Assignments - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">

</head>

<body>

<div class="container">

    <h1>Academic Assignment Monitoring</h1>

    <p>
        Admin can monitor assignments posted by faculty.
    </p>


    <?php if ($result->num_rows === 0): ?>

        <p>No academic assignments have been created yet.</p>

    <?php else: ?>

        <table border="1" cellpadding="10" cellspacing="0">

            <thead>

                <tr>

                    <th>Subject</th>

                    <th>Semester</th>

                    <th>Assignment</th>

                    <th>Faculty</th>

                    <th>Due Date</th>

                    <th>Submissions</th>

                    <th>Graded</th>

                    <th>Pending</th>

                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

                <?php while ($assignment = $result->fetch_assoc()): ?>

                    <?php

                    $total_submissions =
                        (int) $assignment["total_submissions"];

                    $graded_submissions =
                        (int) ($assignment["graded_submissions"] ?? 0);

                    $pending_submissions =
                        $total_submissions - $graded_submissions;

                    ?>

                    <tr>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $assignment["subject_code"]
                            );

                            ?>

                            <br>

                            <?php

                            echo htmlspecialchars(
                                $assignment["subject_name"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $assignment["semester"]
                            );

                            ?>

                        </td>


                        <td>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $assignment["title"]
                                );

                                ?>

                            </strong>

                            <br>

                            <?php

                            echo nl2br(
                                htmlspecialchars(
                                    $assignment["description"]
                                )
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $assignment["faculty_name"]
                            );

                            ?>

                            <br>

                            <small>

                                <?php

                                echo htmlspecialchars(
                                    $assignment["faculty_email"]
                                );

                                ?>

                            </small>

                        </td>


                        <td>

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

                        </td>


                        <td>

                            <?php

                            echo $total_submissions;

                            ?>

                        </td>


                        <td>

                            <?php

                            echo $graded_submissions;

                            ?>

                        </td>


                        <td>

                            <?php

                            echo $pending_submissions;

                            ?>

                        </td>

                        <td>

                            <a
                                href="assignment_submissions.php?assignment_id=<?php
                                echo (int) $assignment["id"];
                                ?>"
                            >
                                View Submissions
                            </a>

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

$stmt->close();

?>