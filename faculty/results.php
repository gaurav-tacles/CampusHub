<?php

session_start();

require_once "../config/db.php";

// Only faculty can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "faculty") {
    header("Location: ../auth/login.php");
    exit;
}

$faculty_id = $_SESSION["user_id"];

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Submit Results for Admin Review
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_results"])) {

    $faculty_subject_id = intval($_POST["faculty_subject_id"]);

    // Verify that this subject actually belongs to logged-in faculty
    $verify_sql = "
        SELECT 
            fs.id,
            fs.semester,
            s.subject_code,
            s.subject_name
        FROM faculty_subjects fs
        INNER JOIN subjects s ON fs.subject_id = s.id
        WHERE fs.id = ?
        AND fs.faculty_id = ?
    ";

    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $faculty_subject_id, $faculty_id);
    $verify_stmt->execute();

    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {

        $error = "Invalid subject selection.";

    } else {

        $subject_data = $verify_result->fetch_assoc();
        $semester = $subject_data["semester"];

        /*
        |--------------------------------------------------------------------------
        | Count all students in this semester
        |--------------------------------------------------------------------------
        */

        $student_sql = "
            SELECT COUNT(*) AS total_students
            FROM users u
            INNER JOIN student_profiles sp
                ON u.id = sp.user_id
            WHERE u.role = 'student'
            AND sp.semester = ?
        ";

        $student_stmt = $conn->prepare($student_sql);
        $student_stmt->bind_param("i", $semester);
        $student_stmt->execute();

        $student_result = $student_stmt->get_result();
        $student_data = $student_result->fetch_assoc();

        $total_students = (int)$student_data["total_students"];


        /*
        |--------------------------------------------------------------------------
        | Count draft results
        |--------------------------------------------------------------------------
        */

        $draft_sql = "
            SELECT COUNT(*) AS draft_count
            FROM results
            WHERE faculty_subject_id = ?
            AND status = 'draft'
        ";

        $draft_stmt = $conn->prepare($draft_sql);
        $draft_stmt->bind_param("i", $faculty_subject_id);
        $draft_stmt->execute();

        $draft_result = $draft_stmt->get_result();
        $draft_data = $draft_result->fetch_assoc();

        $draft_count = (int)$draft_data["draft_count"];


        /*
        |--------------------------------------------------------------------------
        | Count all results for this subject
        |--------------------------------------------------------------------------
        */

        $result_sql = "
            SELECT COUNT(*) AS result_count
            FROM results
            WHERE faculty_subject_id = ?
        ";

        $result_stmt = $conn->prepare($result_sql);
        $result_stmt->bind_param("i", $faculty_subject_id);
        $result_stmt->execute();

        $result_count_data = $result_stmt->get_result()->fetch_assoc();

        $result_count = (int)$result_count_data["result_count"];


        /*
        |--------------------------------------------------------------------------
        | Validation before submission
        |--------------------------------------------------------------------------
        */

        if ($total_students === 0) {

            $error = "There are no students in this semester.";

        } elseif ($result_count !== $total_students) {

            $error = "Cannot submit yet. Marks must be entered for every student.";

        } elseif ($draft_count !== $total_students) {

            $error = "All student results must be in Draft status before submission.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Submit all draft results
            |--------------------------------------------------------------------------
            */

            $update_sql = "
                UPDATE results
                SET
                    status = 'submitted',
                    submitted_at = CURRENT_TIMESTAMP
                WHERE faculty_subject_id = ?
                AND status = 'draft'
            ";

            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $faculty_subject_id);

            if ($update_stmt->execute()) {

                $message = "Marks submitted successfully for Admin review.";

            } else {

                $error = "Failed to submit marks.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Faculty's Assigned Subjects
|--------------------------------------------------------------------------
*/

$subjects_sql = "
    SELECT
        fs.id AS faculty_subject_id,
        fs.semester,
        s.subject_code,
        s.subject_name
    FROM faculty_subjects fs
    INNER JOIN subjects s
        ON fs.subject_id = s.id
    WHERE fs.faculty_id = ?
    ORDER BY fs.semester, s.subject_name
";

$subjects_stmt = $conn->prepare($subjects_sql);
$subjects_stmt->bind_param("i", $faculty_id);
$subjects_stmt->execute();

$subjects_result = $subjects_stmt->get_result();


/*
|--------------------------------------------------------------------------
| Selected Subject
|--------------------------------------------------------------------------
*/

$selected_subject_id = isset($_GET["faculty_subject_id"])
    ? intval($_GET["faculty_subject_id"])
    : 0;

$students_result = null;
$selected_subject = null;


/*
|--------------------------------------------------------------------------
| Load Selected Subject
|--------------------------------------------------------------------------
*/

if ($selected_subject_id > 0) {

    $selected_sql = "
        SELECT
            fs.id AS faculty_subject_id,
            fs.semester,
            s.subject_code,
            s.subject_name
        FROM faculty_subjects fs
        INNER JOIN subjects s
            ON fs.subject_id = s.id
        WHERE fs.id = ?
        AND fs.faculty_id = ?
    ";

    $selected_stmt = $conn->prepare($selected_sql);
    $selected_stmt->bind_param(
        "ii",
        $selected_subject_id,
        $faculty_id
    );
    $selected_stmt->execute();

    $selected_result = $selected_stmt->get_result();

    if ($selected_result->num_rows > 0) {

        $selected_subject = $selected_result->fetch_assoc();

        $semester = $selected_subject["semester"];


        /*
        |--------------------------------------------------------------------------
        | Load Students + Results
        |--------------------------------------------------------------------------
        */

        $students_sql = "
            SELECT
                u.id AS student_id,
                u.name,
                u.email,
                sp.student_id AS student_number,
                r.id AS result_id,
                r.marks,
                r.status,
                r.faculty_comment,
                r.admin_comment,
                r.submitted_at,
                r.reviewed_at,
                r.published_at
            FROM users u
            INNER JOIN student_profiles sp
                ON u.id = sp.user_id

            LEFT JOIN results r
                ON r.student_id = u.id
                AND r.faculty_subject_id = ?

            WHERE u.role = 'student'
            AND sp.semester = ?

            ORDER BY sp.student_id
        ";

        $students_stmt = $conn->prepare($students_sql);

        $students_stmt->bind_param(
            "ii",
            $selected_subject_id,
            $semester
        );

        $students_stmt->execute();

        $students_result = $students_stmt->get_result();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Review Results - CampusHub</title>

    <link rel="stylesheet"
          href="../assets/style.css">

</head>

<body>

<div class="container">

    <h1>Review Results</h1>

    <p>
        Review your students' marks before submitting them to Admin.
    </p>


    <!-- Messages -->

    <?php if ($message): ?>

        <p style="color: green;">
            <?= htmlspecialchars($message) ?>
        </p>

    <?php endif; ?>


    <?php if ($error): ?>

        <p style="color: red;">
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>


    <!-- Subject Selection -->

    <form method="GET">

        <label for="faculty_subject_id">
            Select Subject:
        </label>

        <select
            name="faculty_subject_id"
            id="faculty_subject_id"
            required
        >

            <option value="">
                -- Select Subject --
            </option>

            <?php while ($subject = $subjects_result->fetch_assoc()): ?>

                <option
                    value="<?= $subject["faculty_subject_id"] ?>"
                    <?= ($selected_subject_id == $subject["faculty_subject_id"]) ? "selected" : "" ?>
                >

                    Semester <?= htmlspecialchars($subject["semester"]) ?>
                    -
                    <?= htmlspecialchars($subject["subject_code"]) ?>
                    -
                    <?= htmlspecialchars($subject["subject_name"]) ?>

                </option>

            <?php endwhile; ?>

        </select>

        <button type="submit">
            Review
        </button>

    </form>


    <?php if ($selected_subject && $students_result): ?>

        <hr>

        <h2>
            <?= htmlspecialchars($selected_subject["subject_name"]) ?>
        </h2>

        <p>
            <strong>Code:</strong>
            <?= htmlspecialchars($selected_subject["subject_code"]) ?>
            |
            <strong>Semester:</strong>
            <?= htmlspecialchars($selected_subject["semester"]) ?>
        </p>


        <!-- Results Table -->

        <table border="1" cellpadding="10" cellspacing="0">

            <thead>

                <tr>

                    <th>Student ID</th>

                    <th>Name</th>

                    <th>Email</th>

                    <th>Marks</th>

                    <th>Status</th>

                </tr>

            </thead>

            <tbody>

            <?php

            $has_missing_marks = false;

            $has_locked_results = false;

            $student_count = 0;

            ?>

            <?php while ($student = $students_result->fetch_assoc()): ?>

                <?php

                $student_count++;

                $status = $student["status"];

                $marks = $student["marks"];

                if ($marks === null) {
                    $has_missing_marks = true;
                }

                if (
                    $status === "submitted" ||
                    $status === "approved" ||
                    $status === "published"
                ) {
                    $has_locked_results = true;
                }

                ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($student["student_number"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($student["name"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($student["email"]) ?>
                    </td>

                    <td>

                        <?php if ($marks !== null): ?>

                            <?= htmlspecialchars($marks) ?>

                        <?php else: ?>

                            <span style="color: red;">
                                Not entered
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>

                        <?php if ($status === null): ?>

                            <span style="color: red;">
                                Not Saved
                            </span>

                        <?php elseif ($status === "draft"): ?>

                            <span>
                                Draft
                            </span>

                        <?php elseif ($status === "submitted"): ?>

                            <span>
                                Submitted
                            </span>

                        <?php elseif ($status === "approved"): ?>

                            <span>
                                Approved
                            </span>

                        <?php elseif ($status === "rejected"): ?>

                            <span style="color: red;">
                                Rejected
                            </span>

                        <?php elseif ($status === "published"): ?>

                            <span>
                                Published
                            </span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>


        <br>


        <?php if ($student_count === 0): ?>

            <p style="color: red;">
                No students found for this semester.
            </p>


        <?php elseif ($has_missing_marks): ?>

            <p style="color: red;">
                ⚠️ Some students do not have marks yet.
                Complete all marks before submitting.
            </p>


        <?php elseif ($has_locked_results): ?>

            <p>
                These results have already been submitted or processed.
            </p>


        <?php else: ?>

            <!-- Submit to Admin -->

            <form method="POST">

                <input
                    type="hidden"
                    name="faculty_subject_id"
                    value="<?= $selected_subject_id ?>"
                >

                <button
                    type="submit"
                    name="submit_results"
                    onclick="return confirm(
                        'Are you sure you want to submit these marks to Admin for review?'
                    );"
                >
                    Submit Results for Admin Review
                </button>

            </form>

        <?php endif; ?>


        <br>

        <a href="marks.php">
            ← Back to Enter Marks
        </a>

    <?php endif; ?>


    <br><br>

    <a href="dashboard.php">
        ← Faculty Dashboard
    </a>

</div>

</body>

</html>