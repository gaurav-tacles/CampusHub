
<?php

session_start();

require_once "../config/db.php";
require_once "../config/notifications.php";


/*
|--------------------------------------------------------------------------
| FACULTY ACCESS PROTECTION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"])
    || !isset($_SESSION["role"])
    || $_SESSION["role"] !== "faculty"
) {
    header("Location: ../auth/login.php");
    exit;
}

$faculty_id = (int) $_SESSION["user_id"];

$message = "";
$error = "";


/*
|--------------------------------------------------------------------------
| GRADE SUBMISSION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["grade_submission"])
) {

    $submission_id =
        (int) ($_POST["submission_id"] ?? 0);

    $marks =
        trim($_POST["marks"] ?? "");

    $faculty_feedback =
        trim($_POST["faculty_feedback"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | VALIDATE SUBMISSION ID
    |--------------------------------------------------------------------------
    */

    if ($submission_id <= 0) {

        $error = "Invalid submission.";

    /*
    |--------------------------------------------------------------------------
    | VALIDATE MARKS
    |--------------------------------------------------------------------------
    */

    } elseif ($marks === "") {

        $error = "Marks are required.";

    } elseif (!is_numeric($marks)) {

        $error = "Marks must be a number.";

    } elseif ((float) $marks < 0 || (float) $marks > 30) {

        $error = "Marks must be between 0 and 30.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | VERIFY SUBMISSION BELONGS TO LOGGED-IN FACULTY
        |--------------------------------------------------------------------------
        */

        $verify_sql = "
            SELECT
                sub.id,
                sub.student_id,
                a.title AS assignment_title

            FROM assignment_submissions sub

            INNER JOIN assignments a
                ON sub.assignment_id = a.id

            INNER JOIN faculty_subjects fs
                ON a.faculty_subject_id = fs.id

            WHERE sub.id = ?
              AND fs.faculty_id = ?
        ";

        $verify_stmt =
            $conn->prepare($verify_sql);


        if (!$verify_stmt) {

            $error =
                "Failed to verify submission.";

        } else {

            $verify_stmt->bind_param(
                "ii",
                $submission_id,
                $faculty_id
            );

            $verify_stmt->execute();

            $verify_result =
                $verify_stmt->get_result();


            /*
            |--------------------------------------------------------------------------
            | SUBMISSION NOT FOUND
            |--------------------------------------------------------------------------
            */

            if ($verify_result->num_rows === 0) {

                $error =
                    "Submission not found or you do not have permission.";

            } else {

                $submission_data =
                    $verify_result->fetch_assoc();

                $student_id =
                    (int) $submission_data["student_id"];

                $assignment_title =
                    $submission_data["assignment_title"];


                /*
                |--------------------------------------------------------------------------
                | UPDATE GRADE
                |--------------------------------------------------------------------------
                */

                $update_sql = "
                    UPDATE assignment_submissions

                    SET
                        marks = ?,
                        faculty_feedback = ?,
                        status = 'graded'

                    WHERE id = ?
                ";

                $update_stmt =
                    $conn->prepare($update_sql);


                if (!$update_stmt) {

                    $error =
                        "Failed to prepare grade update.";

                } else {

                    $marks_value =
                        (float) $marks;


                    $update_stmt->bind_param(
                        "dsi",
                        $marks_value,
                        $faculty_feedback,
                        $submission_id
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | SAVE GRADE
                    |--------------------------------------------------------------------------
                    */

                    if ($update_stmt->execute()) {


                        /*
                        |--------------------------------------------------------------------------
                        | CREATE STUDENT NOTIFICATION
                        |--------------------------------------------------------------------------
                        */

                        createNotification(
                            $conn,
                            $student_id,
                            "Assignment Graded",
                            "Your assignment \""
                            . $assignment_title
                            . "\" has been graded. Marks: "
                            . $marks_value
                            . "/30.",
                            "assignment"
                        );


                        $message =
                            "Grade saved successfully.";

                    } else {

                        $error =
                            "Failed to save grade: "
                            . $update_stmt->error;
                    }


                    $update_stmt->close();
                }
            }


            $verify_stmt->close();
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD FACULTY ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$assignments_sql = "
    SELECT
        a.id,
        a.title,
        a.description,
        a.due_date,

        fs.semester,

        s.subject_code,
        s.subject_name

    FROM assignments a

    INNER JOIN faculty_subjects fs
        ON a.faculty_subject_id = fs.id

    INNER JOIN subjects s
        ON fs.subject_id = s.id

    WHERE fs.faculty_id = ?

    ORDER BY a.due_date ASC
";


$assignments_stmt =
    $conn->prepare($assignments_sql);


if (!$assignments_stmt) {

    die("Failed to load assignments.");

}


$assignments_stmt->bind_param(
    "i",
    $faculty_id
);


$assignments_stmt->execute();


$assignments_result =
    $assignments_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Assignment Submissions - CampusHub</title>

    <link
        rel="stylesheet"
        href="../assets/style.css"
    >

</head>


<body>

<div class="container">


    <h1>Assignment Submissions</h1>


    <?php if ($message !== ""): ?>

        <p style="color: green;">
            <?php
            echo htmlspecialchars($message);
            ?>
        </p>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <p style="color: red;">
            <?php
            echo htmlspecialchars($error);
            ?>
        </p>

    <?php endif; ?>


    <?php if ($assignments_result->num_rows === 0): ?>

        <p>No assignments created yet.</p>

    <?php else: ?>


        <?php while (
            $assignment =
            $assignments_result->fetch_assoc()
        ): ?>


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
                        $assignment["subject_code"]
                        . " - "
                        . $assignment["subject_name"]
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


                <?php
                /*
                |--------------------------------------------------------------------------
                | LOAD SUBMISSIONS FOR THIS ASSIGNMENT
                |--------------------------------------------------------------------------
                */

                $submissions_sql = "
                    SELECT
                        sub.id,
                        sub.file_name,
                        sub.file_path,
                        sub.submitted_at,
                        sub.status,
                        sub.marks,
                        sub.faculty_feedback,

                        u.name,
                        u.email

                    FROM assignment_submissions sub

                    INNER JOIN users u
                        ON sub.student_id = u.id

                    WHERE sub.assignment_id = ?

                    ORDER BY sub.submitted_at DESC
                ";


                $submissions_stmt =
                    $conn->prepare(
                        $submissions_sql
                    );


                $submissions_stmt->bind_param(
                    "i",
                    $assignment["id"]
                );


                $submissions_stmt->execute();


                $submissions_result =
                    $submissions_stmt->get_result();
                ?>


                <h3>
                    Student Submissions
                </h3>


                <?php if (
                    $submissions_result->num_rows === 0
                ): ?>

                    <p>
                        No students have submitted
                        this assignment yet.
                    </p>

                <?php else: ?>


                    <?php while (
                        $submission =
                        $submissions_result->fetch_assoc()
                    ): ?>


                        <div class="submission-card">


                            <p>

                                <strong>
                                    Student:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    $submission["name"]
                                );
                                ?>

                            </p>


                            <p>

                                <strong>
                                    Email:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    $submission["email"]
                                );
                                ?>

                            </p>


                            <p>

                                <strong>
                                    Submitted:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    date(
                                        "d M Y, h:i A",
                                        strtotime(
                                            $submission[
                                                "submitted_at"
                                            ]
                                        )
                                    )
                                );
                                ?>

                            </p>


                            <p>

                                <strong>
                                    File:
                                </strong>

                                <a
                                    href="../<?php
                                    echo htmlspecialchars(
                                        $submission[
                                            "file_path"
                                        ]
                                    );
                                    ?>"
                                    target="_blank"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $submission[
                                            "file_name"
                                        ]
                                    );
                                    ?>

                                </a>

                            </p>


                            <p>

                                <strong>
                                    Status:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    ucfirst(
                                        $submission[
                                            "status"
                                        ]
                                    )
                                );
                                ?>

                            </p>


                            <form method="POST">


                                <input
                                    type="hidden"
                                    name="submission_id"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $submission["id"]
                                    );
                                    ?>"
                                >


                                <label>
                                    Marks (0 - 30):
                                </label>


                                <input
                                    type="number"
                                    name="marks"
                                    min="0"
                                    max="30"
                                    step="0.01"
                                    value="<?php
                                    echo $submission["marks"]
                                        !== null
                                        ? htmlspecialchars(
                                            $submission["marks"]
                                        )
                                        : "";
                                    ?>"
                                    required
                                >


                                <br><br>


                                <label>
                                    Faculty Feedback:
                                </label>


                                <textarea
                                    name="faculty_feedback"
                                    rows="4"
                                    placeholder="Enter feedback for the student..."
                                ><?php
                                echo htmlspecialchars(
                                    $submission[
                                        "faculty_feedback"
                                    ] ?? ""
                                );
                                ?></textarea>


                                <br><br>


                                <button
                                    type="submit"
                                    name="grade_submission"
                                >
                                    Save Grade
                                </button>


                            </form>


                        </div>


                        <hr>


                    <?php endwhile; ?>


                <?php endif; ?>


                <?php
                $submissions_stmt->close();
                ?>


            </div>


        <?php endwhile; ?>


    <?php endif; ?>


</div>


</body>

</html>