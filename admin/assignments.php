<?php
session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    echo "Access denied.";
    exit;
}

$message = "";
$messageType = "";

if (isset($_GET["delete"])) {

    $assignmentId = (int) $_GET["delete"];

    if ($assignmentId > 0) {

        $stmt = $conn->prepare(
            "DELETE FROM faculty_subjects
             WHERE id = ?"
        );

        $stmt->bind_param("i", $assignmentId);

        try {

            if ($stmt->execute()) {

                if ($stmt->affected_rows > 0) {
                    header("Location: assignments.php?deleted=1");
                    exit;
                } else {
                    header("Location: assignments.php?delete_error=1");
                    exit;
                }

            }

        } catch (mysqli_sql_exception $e) {

            header("Location: assignments.php?delete_error=1");
            exit;
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Handle Assignment
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $facultyId = (int)($_POST["faculty_id"] ?? 0);
    $subjectId = (int)($_POST["subject_id"] ?? 0);
    $semester = (int)($_POST["semester"] ?? 0);

    if ($facultyId <= 0 || $subjectId <= 0 || $semester < 1 || $semester > 8) {

        $message = "Please select valid faculty, subject and semester.";
        $messageType = "error";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Verify that selected faculty is actually a faculty user
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE id = ?
             AND role = 'faculty'"
        );

        $stmt->bind_param("i", $facultyId);
        $stmt->execute();

        $facultyResult = $stmt->get_result();

        if ($facultyResult->num_rows === 0) {

            $message = "Invalid faculty selected.";
            $messageType = "error";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Verify subject belongs to selected semester
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                "SELECT id
                 FROM subjects
                 WHERE id = ?
                 AND semester = ?"
            );

            $stmt->bind_param("ii", $subjectId, $semester);
            $stmt->execute();

            $subjectResult = $stmt->get_result();

            if ($subjectResult->num_rows === 0) {

                $message = "Selected subject does not belong to this semester.";
                $messageType = "error";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Create Assignment
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare(
                    "INSERT INTO faculty_subjects
                     (faculty_id, subject_id, semester)
                     VALUES (?, ?, ?)"
                );

                $stmt->bind_param(
                    "iii",
                    $facultyId,
                    $subjectId,
                    $semester
                );

                try {

                    if ($stmt->execute()) {

                        $message = "Faculty assignment created successfully.";
                        $messageType = "success";

                    }

                } catch (mysqli_sql_exception $e) {

                    if ($e->getCode() === 1062) {

                        $message = "This faculty is already assigned to this subject and semester.";

                    } else {

                        $message = "Assignment could not be created.";

                    }

                    $messageType = "error";
                }
            }
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Get Faculty List
|--------------------------------------------------------------------------
*/

$facultyQuery = $conn->query(
    "SELECT id, name, email
     FROM users
     WHERE role = 'faculty'
     ORDER BY name"
);


/*
|--------------------------------------------------------------------------
| Get All Subjects
|--------------------------------------------------------------------------
*/

$subjectQuery = $conn->query(
    "SELECT id, subject_code, subject_name, semester
     FROM subjects
     ORDER BY semester, subject_name"
);


/*
|--------------------------------------------------------------------------
| Get Existing Assignments
|--------------------------------------------------------------------------
*/

$assignmentQuery = $conn->query(
    "SELECT
        fs.id,
        u.name AS faculty_name,
        s.subject_code,
        s.subject_name,
        fs.semester
     FROM faculty_subjects fs
     INNER JOIN users u
        ON fs.faculty_id = u.id
     INNER JOIN subjects s
        ON fs.subject_id = s.id
     ORDER BY fs.semester, u.name, s.subject_name"
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Faculty Assignments - CampusHub</title>

<link rel="stylesheet" href="../assets/style.css">

</head>

<body>

<h1>CampusHub</h1>

<h2>Faculty Subject Assignments</h2>

<p>
    Welcome,
    <?php echo htmlspecialchars($_SESSION["name"]); ?>
</p>

<?php if ($message !== ""): ?>

    <p>
        <?php echo htmlspecialchars($message); ?>
    </p>

<?php endif; ?>


<h3>Assign Faculty</h3>

<form method="POST">

    <label for="faculty_id">Faculty</label>
    <br>

    <select name="faculty_id" id="faculty_id" required>

        <option value="">Select Faculty</option>

        <?php while ($faculty = $facultyQuery->fetch_assoc()): ?>

            <option value="<?php echo $faculty["id"]; ?>">

                <?php echo htmlspecialchars($faculty["name"]); ?>

                -

                <?php echo htmlspecialchars($faculty["email"]); ?>

            </option>

        <?php endwhile; ?>

    </select>

    <br><br>


    <label for="semester">Semester</label>
    <br>

    <select name="semester" id="semester" required>

        <option value="">Select Semester</option>

        <?php for ($i = 1; $i <= 8; $i++): ?>

            <option value="<?php echo $i; ?>">
                Semester <?php echo $i; ?>
            </option>

        <?php endfor; ?>

    </select>

    <br><br>


    <label for="subject_id">Subject</label>
    <br>

    <select name="subject_id" id="subject_id" required>

        <option value="">Select Semester First</option>

        <?php while ($subject = $subjectQuery->fetch_assoc()): ?>

            <option
                value="<?php echo $subject["id"]; ?>"
                data-semester="<?php echo $subject["semester"]; ?>"
            >

                <?php echo htmlspecialchars($subject["subject_name"]); ?>

            </option>

        <?php endwhile; ?>

    </select>

    <br><br>

    <button type="submit">
        Assign Faculty
    </button>

</form>


<hr>


<h3>Existing Assignments</h3>

<table border="1" cellpadding="8">

    <tr>
        <th>Faculty</th>
        <th>Subject</th>
        <th>Semester</th>
        <th>Action</th>
    </tr>

    <?php while ($assignment = $assignmentQuery->fetch_assoc()): ?>

        <tr>

            <td>
                <?php echo htmlspecialchars($assignment["faculty_name"]); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($assignment["subject_name"]); ?>
                (<?php echo htmlspecialchars($assignment["subject_code"]); ?>)
            </td>

            <td>
                Semester <?php echo htmlspecialchars($assignment["semester"]); ?>
            </td>

            <td>
                <a
                    href="assignments.php?delete=<?php echo $assignment["id"]; ?>"
                    onclick="return confirm('Are you sure you want to remove this assignment?');"
                >
                    Delete
                </a>
            </td>

        </tr>
    <?php endwhile; ?>

</table>

<br>

<a href="dashboard.php">Back to Dashboard</a>

<script>

const semesterSelect = document.getElementById("semester");
const subjectSelect = document.getElementById("subject_id");

semesterSelect.addEventListener("change", function () {

    const selectedSemester = this.value;

    // Reset subject selection
    subjectSelect.value = "";

    const options = subjectSelect.querySelectorAll("option");

    options.forEach(function (option) {

        if (option.value === "") {
            option.disabled = false;
            option.hidden = false;
            return;
        }

        const subjectSemester = option.dataset.semester;

        if (subjectSemester === selectedSemester) {
            option.disabled = false;
            option.hidden = false;
        } else {
            option.disabled = true;
            option.hidden = true;
        }

    });

});

</script>

</body>

</html>
