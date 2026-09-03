<?php

session_start();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| Login Protection
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;

}


/*
|--------------------------------------------------------------------------
| Role Protection
|--------------------------------------------------------------------------
*/

if ($_SESSION["role"] !== "student") {

    header("Location: ../auth/login.php");
    exit;

}


$userId = $_SESSION["user_id"];

$errors = [];

$studentId = "";
$course = "";
$semester = "";

$profileExists = false;

$checkProfile = $conn->prepare(
    "SELECT student_id, course, semester
     FROM student_profiles
     WHERE user_id = ?"
);

$checkProfile->bind_param(
    "i",
    $userId
);

$checkProfile->execute();

$profileResult = $checkProfile->get_result();

if ($profileResult->num_rows === 1) {

    $profile = $profileResult->fetch_assoc();

    $studentId = $profile["student_id"];
    $course = $profile["course"];
    $semester = $profile["semester"];

    $profileExists = true;
}

$checkProfile->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $studentId = trim($_POST["student_id"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $semester = $_POST["semester"] ?? "";


    // Student ID validation
    if ($studentId === "") {

        $errors[] = "Student ID is required.";

    }


    // Course validation
    if ($course === "") {

        $errors[] = "Course is required.";

    }


    // Semester validation
    if ($semester === "") {

        $errors[] = "Semester is required.";

    } elseif (!is_numeric($semester)) {

        $errors[] = "Semester must be a number.";

    } elseif ($semester < 1 || $semester > 8) {

        $errors[] = "Semester must be between 1 and 8.";

    }

   if (empty($errors)) {

    $semester = (int)$semester;

    // Check again whether this user already has a profile
    $checkProfile = $conn->prepare(
        "SELECT id
         FROM student_profiles
         WHERE user_id = ?"
    );

    $checkProfile->bind_param(
        "i",
        $userId
    );

    $checkProfile->execute();

    $profileResult = $checkProfile->get_result();

    $profileExists = $profileResult->num_rows === 1;

    $checkProfile->close();


    if ($profileExists) {

        // Update existing profile
        $stmt = $conn->prepare(
            "UPDATE student_profiles
             SET student_id = ?,
                 course = ?,
                 semester = ?
             WHERE user_id = ?"
        );

        $stmt->bind_param(
            "ssii",
            $studentId,
            $course,
            $semester,
            $userId
        );

    } else {

        // Create new profile
        $stmt = $conn->prepare(
            "UPDATE student_profiles
            SET course = ?,
                semester = ?
            WHERE user_id = ?"
        );

        $stmt->bind_param(
            "sii",
            $course,
            $semester,
            $userId
        );
    }


    if ($stmt->execute()) {

        if ($profileExists) {
            
            echo "<p>Profile updated successfully!</p>";

        } else {

            echo "<p>Profile created successfully!</p>";
        }

    } else {

        $errors[] = "Unable to save profile.";

    }

    $stmt->close();
}
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

    <title>Student Profile - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>

    <h1>CampusHub</h1>

    <h2>Complete Your Student Profile</h2>

    <p>
        Welcome,
        <?php echo htmlspecialchars($_SESSION["name"]); ?>
    </p>

    <?php if (!empty($errors)): ?>

    <div class="error-message">

        <?php foreach ($errors as $error): ?>

            <p>
                <?php echo htmlspecialchars($error); ?>
            </p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

    <form action="profile.php" method="POST">

        <label>Student ID:</label>
        <input
            type="text"
            name="student_id"
            value="<?php echo htmlspecialchars($studentId); ?>"
            required
            <?php if ($profileExists) echo "readonly"; ?>
        >

        <br><br>

        <label>Course:</label>
        <input
            type="text"
            name="course"
            value="<?php echo htmlspecialchars($course); ?>"
            required
        >

        <br><br>

        <label>Semester:</label>
        <input
            type="number"
            name="semester"
            min="1"
            max="8"
            value="<?php echo htmlspecialchars($semester); ?>"
            required
        >

        <br><br>

        <button type="submit">
            Save Profile
        </button>

    </form>

    <br>

    <a href="dashboard.php">
        Back to Dashboard
    </a>

</body>
</html>