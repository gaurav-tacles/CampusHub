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
| DELETE ASSIGNMENT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_assignment"])) {

    $assignment_id = intval($_POST["assignment_id"] ?? 0);

    if ($assignment_id <= 0) {

        $error = "Invalid assignment.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Make sure this assignment belongs to the logged-in faculty
        |--------------------------------------------------------------------------
        */

        $delete_sql = "
            DELETE a
            FROM assignments a

            INNER JOIN faculty_subjects fs
                ON a.faculty_subject_id = fs.id

            WHERE a.id = ?
              AND fs.faculty_id = ?
        ";

        $delete_stmt = $conn->prepare($delete_sql);

        $delete_stmt->bind_param(
            "ii",
            $assignment_id,
            $faculty_id
        );

        if ($delete_stmt->execute()) {

            if ($delete_stmt->affected_rows > 0) {
                $message = "Assignment deleted successfully.";
            } else {
                $error = "Assignment not found or you do not have permission.";
            }

        } else {

            $error = "Failed to delete assignment.";

        }

        $delete_stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| CREATE ASSIGNMENT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_assignment"])) {

    $faculty_subject_id = intval($_POST["faculty_subject_id"] ?? 0);
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $due_date = trim($_POST["due_date"] ?? "");

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($faculty_subject_id <= 0) {

        $error = "Please select a subject.";

    } elseif ($title === "") {

        $error = "Assignment title is required.";

    } elseif ($description === "") {

        $error = "Assignment description is required.";

    } elseif ($due_date === "") {

        $error = "Due date is required.";

    } elseif (strtotime($due_date) <= time()) {

        $error = "Due date and time must be in the future.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Verify Faculty Owns This Subject Assignment
        |--------------------------------------------------------------------------
        */

        $verify_sql = "
            SELECT fs.id
            FROM faculty_subjects fs
            WHERE fs.id = ?
              AND fs.faculty_id = ?
        ";

        $verify_stmt = $conn->prepare($verify_sql);

        $verify_stmt->bind_param(
            "ii",
            $faculty_subject_id,
            $faculty_id
        );

        $verify_stmt->execute();

        $verify_result = $verify_stmt->get_result();

        if ($verify_result->num_rows === 0) {

            $error = "You are not assigned to this subject.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Insert Assignment
            |--------------------------------------------------------------------------
            */

            $insert_sql = "
                INSERT INTO assignments
                (
                    faculty_subject_id,
                    title,
                    description,
                    due_date
                )
                VALUES (?, ?, ?, ?)
            ";

            $insert_stmt = $conn->prepare($insert_sql);

            $insert_stmt->bind_param(
                "isss",
                $faculty_subject_id,
                $title,
                $description,
                $due_date
            );

            if ($insert_stmt->execute()) {

                $message = "Assignment created successfully.";

            } else {

                $error = "Failed to create assignment.";

            }

            $insert_stmt->close();
        }

        $verify_stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| LOAD FACULTY SUBJECTS
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

$subjects_stmt->bind_param(
    "i",
    $faculty_id
);

$subjects_stmt->execute();

$subjects_result = $subjects_stmt->get_result();

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
        a.created_at,

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

$assignments_stmt = $conn->prepare($assignments_sql);

$assignments_stmt->bind_param(
    "i",
    $faculty_id
);

$assignments_stmt->execute();

$assignments_result = $assignments_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Assignments - Faculty - CampusHub</title>

    <link
        rel="stylesheet"
        href="../assets/style.css"
    >

</head>

<body>

<div class="container">

    <h1>Assignment Management</h1>

    <p>
        Create and manage assignments for your assigned subjects.
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


    <!-- Create Assignment -->

    <hr>

    <h2>Create Assignment</h2>

    <form method="POST">

        <label for="faculty_subject_id">
            Subject
        </label>

        <br>

        <select
            name="faculty_subject_id"
            id="faculty_subject_id"
            required
        >

            <option value="">
                -- Select Subject --
            </option>

            <?php while ($subject = $subjects_result->fetch_assoc()): ?>

                <option value="<?= $subject["faculty_subject_id"] ?>">

                    Semester
                    <?= htmlspecialchars($subject["semester"]) ?>

                    -

                    <?= htmlspecialchars($subject["subject_code"]) ?>

                    -

                    <?= htmlspecialchars($subject["subject_name"]) ?>

                </option>

            <?php endwhile; ?>

        </select>

        <br><br>


        <label for="title">
            Assignment Title
        </label>

        <br>

        <input
            type="text"
            name="title"
            id="title"
            maxlength="255"
            required
        >

        <br><br>


        <label for="description">
            Description
        </label>

        <br>

        <textarea
            name="description"
            id="description"
            rows="6"
            cols="60"
            required
        ></textarea>

        <br><br>


        <label for="due_date">
            Due Date
        </label>

        <br>

        <input 
            type="datetime-local"
            name="due_date" 
            id="due_date"
            required>

        <br><br>


        <button
            type="submit"
            name="create_assignment"
            value="1"
        >
            Create Assignment
        </button>

    </form>


    <!-- Existing Assignments -->

    <hr>

    <h2>My Assignments</h2>

    <?php if ($assignments_result->num_rows === 0): ?>

        <p>
            You have not created any assignments yet.
        </p>

    <?php else: ?>

        <table
            border="1"
            cellpadding="10"
            cellspacing="0"
        >

            <thead>

                <tr>

                    <th>Subject</th>

                    <th>Title</th>

                    <th>Description</th>

                    <th>Due Date</th>

                    <th>Created</th>

                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

                <?php while ($assignment = $assignments_result->fetch_assoc()): ?>

                    <tr>

                        <td>

                            Semester
                            <?= htmlspecialchars($assignment["semester"]) ?>

                            <br>

                            <?= htmlspecialchars($assignment["subject_code"]) ?>

                            <br>

                            <?= htmlspecialchars($assignment["subject_name"]) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($assignment["title"]) ?>

                        </td>


                        <td>

                            <?= nl2br(
                                htmlspecialchars($assignment["description"])
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($assignment["due_date"]) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($assignment["created_at"]) ?>

                        </td>


                        <td>

                            <form
                                method="POST"
                                onsubmit="return confirm(
                                    'Delete this assignment?'
                                );"
                            >

                                <input
                                    type="hidden"
                                    name="assignment_id"
                                    value="<?= $assignment["id"] ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_assignment"
                                    value="1"
                                >
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    <?php endif; ?>


    <br>

    <a href="dashboard.php">
        ← Back to Faculty Dashboard
    </a>

</div>

<script>
    const dueDateInput = document.getElementById("due_date");

    function setMinDueDate() {
        const now = new Date();

        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, "0");
        const day = String(now.getDate()).padStart(2, "0");
        const hours = String(now.getHours()).padStart(2, "0");
        const minutes = String(now.getMinutes()).padStart(2, "0");

        dueDateInput.min = `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    setMinDueDate();
</script>

</body>

</html>