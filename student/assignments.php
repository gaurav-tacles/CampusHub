<?php

session_start();

require_once "../config/db.php";

// Only students can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = $_SESSION["user_id"];

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| GET STUDENT SEMESTER
|--------------------------------------------------------------------------
*/

$student_sql = "
    SELECT semester
    FROM student_profiles
    WHERE user_id = ?
";

$student_stmt = $conn->prepare($student_sql);

$student_stmt->bind_param(
    "i",
    $student_id
);

$student_stmt->execute();

$student_result = $student_stmt->get_result();

if ($student_result->num_rows === 0) {
    die("Student profile not found.");
}

$student = $student_result->fetch_assoc();

$student_semester = (int) $student["semester"];

$student_stmt->close();

/*
|--------------------------------------------------------------------------
| UPLOAD ASSIGNMENT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_assignment"])) {

    $assignment_id = intval($_POST["assignment_id"] ?? 0);

    if ($assignment_id <= 0) {

        $error = "Invalid assignment.";

    } elseif (!isset($_FILES["assignment_file"])) {

        $error = "Please select a file.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | GET ASSIGNMENT
        |--------------------------------------------------------------------------
        */

        $assignment_sql = "
            SELECT
                a.id,
                a.due_date,
                fs.semester

            FROM assignments a

            INNER JOIN faculty_subjects fs
                ON a.faculty_subject_id = fs.id

            WHERE a.id = ?
              AND fs.semester = ?
        ";

        $assignment_stmt = $conn->prepare($assignment_sql);

        $assignment_stmt->bind_param(
            "ii",
            $assignment_id,
            $student_semester
        );

        $assignment_stmt->execute();

        $assignment_result = $assignment_stmt->get_result();

        if ($assignment_result->num_rows === 0) {

            $error = "Assignment not found or it is not for your semester.";

        } else {

            $assignment = $assignment_result->fetch_assoc();

            /*
            |--------------------------------------------------------------------------
            | CHECK DUE DATE
            |--------------------------------------------------------------------------
            */

            if (strtotime($assignment["due_date"]) < time()) {

                $error = "The submission deadline has passed.";

            } else {

                $file = $_FILES["assignment_file"];

                /*
                |--------------------------------------------------------------------------
                | CHECK UPLOAD ERROR
                |--------------------------------------------------------------------------
                */

                if ($file["error"] !== UPLOAD_ERR_OK) {

                    $error = "File upload failed.";

                /*
                |--------------------------------------------------------------------------
                | MAXIMUM FILE SIZE
                |--------------------------------------------------------------------------
                */

                } elseif ($file["size"] > 10 * 1024 * 1024) {

                    $error = "File size must not exceed 10 MB.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | ALLOWED FILE TYPES
                    |--------------------------------------------------------------------------
                    */

                    $allowed_extensions = [
                        "pdf",
                        "doc",
                        "docx",
                        "ppt",
                        "pptx",
                        "zip"
                    ];

                    $original_name = $file["name"];

                    $extension = strtolower(
                        pathinfo($original_name, PATHINFO_EXTENSION)
                    );

                    if (!in_array($extension, $allowed_extensions, true)) {

                        $error = "Invalid file type. Allowed: PDF, DOC, DOCX, PPT, PPTX, ZIP.";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | CREATE UPLOAD DIRECTORY
                        |--------------------------------------------------------------------------
                        */

                        $upload_directory = "../uploads/assignments/";

                        if (!is_dir($upload_directory)) {
                            mkdir($upload_directory, 0755, true);
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | GENERATE SAFE FILE NAME
                        |--------------------------------------------------------------------------
                        */

                        $safe_file_name =
                            "student_" .
                            $student_id .
                            "_assignment_" .
                            $assignment_id .
                            "_" .
                            bin2hex(random_bytes(8)) .
                            "." .
                            $extension;

                        $file_path = $upload_directory . $safe_file_name;

                        /*
                        |--------------------------------------------------------------------------
                        | MOVE FILE
                        |--------------------------------------------------------------------------
                        */

                        if (move_uploaded_file($file["tmp_name"], $file_path)) {

                            /*
                            |--------------------------------------------------------------------------
                            | SAVE SUBMISSION IN DATABASE
                            |--------------------------------------------------------------------------
                            */

                            $relative_path =
                                "uploads/assignments/" . $safe_file_name;

                            $insert_sql = "
                                INSERT INTO assignment_submissions
                                (
                                    assignment_id,
                                    student_id,
                                    file_name,
                                    file_path
                                )
                                VALUES (?, ?, ?, ?)
                            ";

                            $insert_stmt = $conn->prepare($insert_sql);

                            $insert_stmt->bind_param(
                                "iiss",
                                $assignment_id,
                                $student_id,
                                $original_name,
                                $relative_path
                            );

                            if ($insert_stmt->execute()) {

                                $message = "Assignment submitted successfully.";

                            } else {

                                // Remove uploaded file if database insert fails
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }

                                if ($insert_stmt->errno === 1062) {
                                    $error = "You have already submitted this assignment.";
                                } else {
                                    $error = "Failed to save submission.";
                                }
                            }

                            $insert_stmt->close();

                        } else {

                            $error = "Failed to upload file.";
                        }
                    }
                }
            }
        }

        $assignment_stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| LOAD ASSIGNMENTS FOR STUDENT SEMESTER
|--------------------------------------------------------------------------
*/

$assignments_sql = "
    SELECT
        a.id,
        a.title,
        a.description,
        a.due_date,
        a.created_at,

        s.subject_code,
        s.subject_name,

        fs.semester

    FROM assignments a

    INNER JOIN faculty_subjects fs
        ON a.faculty_subject_id = fs.id

    INNER JOIN subjects s
        ON fs.subject_id = s.id

    WHERE fs.semester = ?

    ORDER BY a.due_date ASC
";

$assignments_stmt = $conn->prepare($assignments_sql);

$assignments_stmt->bind_param(
    "i",
    $student_semester
);

$assignments_stmt->execute();

$assignments_result = $assignments_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Assignments - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">

</head>

<body>

<div class="container">

    <h1>Assignments</h1>

    <p>
        Semester: <?php echo htmlspecialchars($student_semester); ?>
    </p>

    <?php if ($message !== ""): ?>

        <p style="color: green;">
            <?php echo htmlspecialchars($message); ?>
        </p>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <p style="color: red;">
            <?php echo htmlspecialchars($error); ?>
        </p>

    <?php endif; ?>


    <?php if ($assignments_result->num_rows === 0): ?>

        <p>No assignments available for your semester.</p>

    <?php else: ?>

        <?php while ($assignment = $assignments_result->fetch_assoc()): ?>

            <div class="assignment-card">

                <h2>
                    <?php echo htmlspecialchars($assignment["title"]); ?>
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
                    <strong>Description:</strong><br>

                    <?php
                    echo nl2br(
                        htmlspecialchars($assignment["description"])
                    );
                    ?>
                </p>

                <p>
                    <strong>Due Date:</strong>

                    <?php
                    echo htmlspecialchars(
                        date(
                            "d M Y, h:i A",
                            strtotime($assignment["due_date"])
                        )
                    );
                    ?>
                </p>


                <?php
                $check_submission_sql = "
                    SELECT
                        id,
                        file_name,
                        submitted_at,
                        status,
                        marks,
                        faculty_feedback

                    FROM assignment_submissions

                    WHERE assignment_id = ?
                      AND student_id = ?
                ";

                $check_submission_stmt =
                    $conn->prepare($check_submission_sql);

                $check_submission_stmt->bind_param(
                    "ii",
                    $assignment["id"],
                    $student_id
                );

                $check_submission_stmt->execute();

                $submission_result =
                    $check_submission_stmt->get_result();

                $submission =
                    $submission_result->fetch_assoc();

                $check_submission_stmt->close();
                ?>


                <?php if ($submission): ?>

                    <?php if ($submission["status"] === "graded"): ?>

                        <p style="color: green;">
                            <strong>✓ Assignment Graded</strong>
                        </p>

                    <?php else: ?>

                        <p style="color: orange;">
                            <strong>✓ Assignment Submitted — Waiting for Faculty Review</strong>
                        </p>

                <?php endif; ?>

                    <p>
                        File:
                        <?php
                        echo htmlspecialchars(
                            $submission["file_name"]
                        );
                        ?>
                    </p>

                    <p>
                        Submitted:
                        <?php
                        echo htmlspecialchars(
                            date(
                                "d M Y, h:i A",
                                strtotime(
                                    $submission["submitted_at"]
                                )
                            )
                        );
                        ?>
                    </p>


                    <?php if ($submission["marks"] !== null): ?>

                        <p>
                            <strong>Marks:</strong>
                            <?php
                            echo htmlspecialchars(
                                $submission["marks"]
                            );
                            ?>/30
                        </p>

                        <?php if (
                            !empty($submission["faculty_feedback"])
                        ): ?>

                            <p>
                                <strong>Faculty Feedback:</strong><br>

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $submission["faculty_feedback"]
                                    )
                                );
                                ?>
                            </p>

                        <?php endif; ?>

                    <?php else: ?>

                        <p>
                            Marks not published yet.
                        </p>

                    <?php endif; ?>


                <?php else: ?>

                    <?php if (
                        strtotime($assignment["due_date"]) >= time()
                    ): ?>

                        <form method="POST"
                              enctype="multipart/form-data">

                            <input
                                type="hidden"
                                name="assignment_id"
                                value="<?php
                                echo htmlspecialchars(
                                    $assignment["id"]
                                );
                                ?>"
                            >

                            <label>
                                Upload Assignment:
                            </label>

                            <input
                                type="file"
                                name="assignment_file"
                                accept=".pdf,.doc,.docx,.ppt,.pptx,.zip"
                                required
                            >

                            <small>
                                Maximum 10 MB.
                                Allowed: PDF, DOC, DOCX, PPT, PPTX, ZIP.
                            </small>

                            <br><br>

                            <button
                                type="submit"
                                name="submit_assignment"
                            >
                                Submit Assignment
                            </button>

                        </form>

                    <?php else: ?>

                        <p style="color: red;">
                            Submission deadline has passed.
                        </p>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

            <hr>

        <?php endwhile; ?>

    <?php endif; ?>

</div>

</body>

</html>