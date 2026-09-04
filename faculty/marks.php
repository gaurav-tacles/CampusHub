<?php

session_start();
require_once "../config/db.php";

/* Only logged-in users */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

/* Only faculty can access this page */
if ($_SESSION["role"] !== "faculty") {
    echo "Access denied.";
    exit;
}

$facultyId = $_SESSION["user_id"];

$selectedAssignment = null;
$students = null;
$facultySubjectId = null;

$message = "";
$messageType = "";


/* ========================================
   GET FACULTY ASSIGNED SUBJECTS
   ======================================== */

$stmt = $conn->prepare(
    "SELECT
        fs.id AS faculty_subject_id,
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


/* ========================================
   SAVE MARKS AS DRAFT
   ======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $facultySubjectId = filter_input(
        INPUT_POST,
        "faculty_subject_id",
        FILTER_VALIDATE_INT
    );

    if ($facultySubjectId === false || $facultySubjectId === null) {
        die("Invalid subject selection.");
    }


    /* Verify assignment belongs to logged-in faculty */

    $stmt = $conn->prepare(
        "SELECT
            fs.id,
            fs.semester,
            s.subject_code,
            s.subject_name
         FROM faculty_subjects fs
         INNER JOIN subjects s
            ON fs.subject_id = s.id
         WHERE fs.id = ?
         AND fs.faculty_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $facultySubjectId,
        $facultyId
    );

    $stmt->execute();

    $assignmentResult = $stmt->get_result();

    if ($assignmentResult->num_rows === 0) {
        die("You are not authorized to access this subject.");
    }

    $selectedAssignment = $assignmentResult->fetch_assoc();

    $semester = $selectedAssignment["semester"];


    /* Check marks data */

    if (!isset($_POST["marks"]) || !is_array($_POST["marks"])) {

        $message = "No marks were submitted.";
        $messageType = "error";

    } else {

        $marksData = $_POST["marks"];

        $savedCount = 0;
        $errorCount = 0;


        foreach ($marksData as $studentId => $mark) {

            $facultyComment = trim(
                $_POST["faculty_comment"][$studentId] ?? ""
            );

            /* Validate student ID */

            $studentId = filter_var(
                $studentId,
                FILTER_VALIDATE_INT
            );

            if ($studentId === false || $studentId === null) {
                $errorCount++;
                continue;
            }


            /* Allow blank marks */

            if ($mark === "") {
                continue;
            }


            /* Validate numeric marks */

            if (!is_numeric($mark)) {
                $errorCount++;
                continue;
            }

            $mark = (float) $mark;


            /* Marks must be between 0 and 100 */

            if ($mark < 0 || $mark > 100) {
                $errorCount++;
                continue;
            }


            /* ========================================
               VERIFY STUDENT BELONGS TO THIS SEMESTER
               ======================================== */

            $stmt = $conn->prepare(
                "SELECT u.id
                 FROM users u
                 INNER JOIN student_profiles sp
                    ON u.id = sp.user_id
                 WHERE u.id = ?
                 AND u.role = 'student'
                 AND sp.semester = ?"
            );

            $stmt->bind_param(
                "ii",
                $studentId,
                $semester
            );

            $stmt->execute();

            $studentResult = $stmt->get_result();

            if ($studentResult->num_rows === 0) {
                $errorCount++;
                continue;
            }


            /* ========================================
               CHECK IF RESULT ALREADY EXISTS
               ======================================== */

            $stmt = $conn->prepare(
                "SELECT id, status
                 FROM results
                 WHERE student_id = ?
                 AND faculty_subject_id = ?"
            );

            $stmt->bind_param(
                "ii",
                $studentId,
                $facultySubjectId
            );

            $stmt->execute();

            $existingResult = $stmt->get_result();


            /* ========================================
               UPDATE EXISTING RESULT
               ======================================== */

            if ($existingResult->num_rows > 0) {

                $existing = $existingResult->fetch_assoc();


                /*
                    Faculty cannot modify results that
                    are already submitted, approved,
                    or published.
                */

                if (
                    $existing["status"] === "submitted" ||
                    $existing["status"] === "approved" ||
                    $existing["status"] === "published"
                ) {
                    $errorCount++;
                    continue;
                }


                /* Update draft or rejected result */

                $stmt = $conn->prepare(
                    "UPDATE results
                    SET marks = ?,
                        faculty_comment = ?,
                        status = 'draft',
                        submitted_at = NULL,
                        reviewed_at = NULL,
                        published_at = NULL
                    WHERE id = ?"
                );

                $stmt->bind_param(
                    "dsi",
                    $mark,
                    $facultyComment,
                    $existing["id"]
                );

                if ($stmt->execute()) {
                    $savedCount++;
                }


            } else {

                /* ========================================
                   CREATE NEW DRAFT RESULT
                   ======================================== */

                    $stmt = $conn->prepare(
                        "INSERT INTO results
                            (
                                student_id,
                                faculty_subject_id,
                                marks,
                                faculty_comment,
                                status
                            )
                        VALUES (?, ?, ?, ?, 'draft')"
                    );

                    $stmt->bind_param(
                        "iids",
                        $studentId,
                        $facultySubjectId,
                        $mark,
                        $facultyComment
                    );
                if ($stmt->execute()) {
                    $savedCount++;
                }
            }
        }


        /* ========================================
           SAVE MESSAGE
           ======================================== */

        if ($savedCount > 0 && $errorCount === 0) {

            $message = $savedCount . " mark(s) saved as draft.";
            $messageType = "success";

        } elseif ($savedCount > 0) {

            $message =
                $savedCount .
                " mark(s) saved, but some marks could not be saved.";

            $messageType = "error";

        } else {

            $message = "No marks were saved.";
            $messageType = "error";
        }
    }
}


/* ========================================
   LOAD SELECTED SUBJECT FROM GET
   ======================================== */

if (
    $facultySubjectId === null &&
    isset($_GET["faculty_subject_id"])
) {

    $facultySubjectId = filter_input(
        INPUT_GET,
        "faculty_subject_id",
        FILTER_VALIDATE_INT
    );

    if (
        $facultySubjectId === false ||
        $facultySubjectId === null
    ) {
        die("Invalid subject selection.");
    }
}


/* ========================================
   LOAD SELECTED ASSIGNMENT + STUDENTS
   ======================================== */

if ($facultySubjectId !== null) {

    /* Verify assignment belongs to faculty */

    $stmt = $conn->prepare(
        "SELECT
            fs.id,
            fs.semester,
            s.subject_code,
            s.subject_name
         FROM faculty_subjects fs
         INNER JOIN subjects s
            ON fs.subject_id = s.id
         WHERE fs.id = ?
         AND fs.faculty_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $facultySubjectId,
        $facultyId
    );

    $stmt->execute();

    $assignmentResult = $stmt->get_result();

    if ($assignmentResult->num_rows === 0) {
        die("You are not authorized to access this subject.");
    }

    $selectedAssignment = $assignmentResult->fetch_assoc();

    $semester = $selectedAssignment["semester"];


    /* Get students from this semester */

    $stmt = $conn->prepare(
        "SELECT
            u.id AS student_user_id,
            u.name,
            u.email,
            sp.student_id,
            sp.course,
            sp.semester
         FROM users u
         INNER JOIN student_profiles sp
            ON u.id = sp.user_id
         WHERE u.role = 'student'
         AND sp.semester = ?
         ORDER BY sp.student_id"
    );

    $stmt->bind_param(
        "i",
        $semester
    );

    $stmt->execute();

    $students = $stmt->get_result();
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Enter Marks - CampusHub</title>

    <link
        rel="stylesheet"
        href="../assets/style.css"
    >

</head>


<body>

    <h1>Enter Marks</h1>


    <p>
        Welcome,
        <?php echo htmlspecialchars($_SESSION["name"]); ?>
    </p>


    <!-- ========================================
         MESSAGE
         ======================================== -->

    <?php if ($message !== ""): ?>

        <p>
            <?php echo htmlspecialchars($message); ?>
        </p>

    <?php endif; ?>


    <!-- ========================================
         SUBJECT SELECTION
         ======================================== -->

    <h2>Select Subject</h2>


    <?php if ($result->num_rows > 0): ?>

        <form
            method="GET"
            action="marks.php"
        >

            <label for="faculty_subject_id">
                Subject:
            </label>


            <select
                name="faculty_subject_id"
                id="faculty_subject_id"
                required
            >

                <option value="">
                    -- Select Subject --
                </option>


                <?php while ($subject = $result->fetch_assoc()): ?>

                    <option
                        value="<?php echo $subject["faculty_subject_id"]; ?>"
                        <?php
                        if (
                            $facultySubjectId ==
                            $subject["faculty_subject_id"]
                        ) {
                            echo "selected";
                        }
                        ?>
                    >

                        Semester
                        <?php echo $subject["semester"]; ?>

                        -

                        <?php
                        echo htmlspecialchars(
                            $subject["subject_name"]
                        );
                        ?>

                    </option>

                <?php endwhile; ?>

            </select>


            <br><br>


            <button type="submit">
                Load Students
            </button>

        </form>


    <?php else: ?>

        <p>
            No subjects have been assigned to you yet.
        </p>

    <?php endif; ?>


    <!-- ========================================
         SELECTED SUBJECT
         ======================================== -->

    <?php if ($selectedAssignment !== null): ?>

        <hr>


        <h2>
            <?php
            echo htmlspecialchars(
                $selectedAssignment["subject_name"]
            );
            ?>
        </h2>


        <p>

            <strong>Subject Code:</strong>

            <?php
            echo htmlspecialchars(
                $selectedAssignment["subject_code"]
            );
            ?>

        </p>


        <p>

            <strong>Semester:</strong>

            <?php
            echo htmlspecialchars(
                $selectedAssignment["semester"]
            );
            ?>

        </p>


        <!-- ========================================
             STUDENTS + MARKS FORM
             ======================================== -->

        <?php if ($students !== null && $students->num_rows > 0): ?>

            <h3>Students</h3>


            <form
                method="POST"
                action="marks.php"
            >

                <input
                    type="hidden"
                    name="faculty_subject_id"
                    value="<?php echo $selectedAssignment["id"]; ?>"
                >


                <table
                    border="1"
                    cellpadding="8"
                >

                    <thead>

                        <tr>

                            <th>Student ID</th>

                            <th>Name</th>

                            <th>Email</th>

                            <th>Course</th>

                            <th>Marks</th>

                            <th>Faculty Comment</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php while ($student = $students->fetch_assoc()): ?>

                            <?php

                            /*
                                Check whether this student already
                                has a draft/rejected result.
                            */

                            $existingMark = "";
                            $existingComment = "";

                            $stmt = $conn->prepare(
                                "SELECT marks, status, faculty_comment
                                 FROM results
                                 WHERE student_id = ?
                                 AND faculty_subject_id = ?"
                            );

                            $stmt->bind_param(
                                "ii",
                                $student["student_user_id"],
                                $facultySubjectId
                            );

                            $stmt->execute();

                            $markResult = $stmt->get_result();

                            if ($markResult->num_rows > 0) {

                                $savedResult = $markResult->fetch_assoc();

                                $existingMark =
                                    $savedResult["marks"];

                                $existingComment =
                                    $savedResult["faculty_comment"] ?? "";
                            }

                            ?>


                            <tr>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $student["student_id"]
                                    );
                                    ?>

                                </td>


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

                                    <?php
                                    echo htmlspecialchars(
                                        $student["course"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <input
                                        type="number"
                                        name="marks[<?php echo $student["student_user_id"]; ?>]"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        placeholder="0-100"
                                        value="<?php echo htmlspecialchars($existingMark); ?>"
                                    >

                                </td>

                                <td>

                                    <textarea
                                        name="faculty_comment[<?php echo $student["student_user_id"]; ?>]"
                                        rows="3"
                                        cols="30"
                                        placeholder="Optional comment..."
                                    ><?php echo htmlspecialchars($existingComment); ?></textarea>

                                </td>

                            </tr>


                        <?php endwhile; ?>

                    </tbody>

                </table>


                <br>


                <button type="submit">
                    Save as Draft
                </button>


            </form>


        <?php else: ?>

            <p>

                No students found in Semester

                <?php
                echo htmlspecialchars(
                    $selectedAssignment["semester"]
                );
                ?>.

            </p>

        <?php endif; ?>

    <?php endif; ?>


    <br>


    <a href="dashboard.php">
        Back to Dashboard
    </a>


</body>

</html>