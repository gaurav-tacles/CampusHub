<?php

session_start();

require_once "../config/db.php";

/* =========================
   STUDENT ACCESS PROTECTION
   ========================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = $_SESSION["user_id"];
$edit_mode = isset($_GET["edit"]) && $_GET["edit"] === "1";
$password_mode = isset($_GET["password"]) && $_GET["password"] === "1";

/* =========================
   FETCH STUDENT PROFILE
   ========================= */

$stmt = $conn->prepare("
    SELECT
        u.id,
        u.name,
        u.email,
        sp.student_id,
        sp.course,
        sp.semester
    FROM users u
    INNER JOIN student_profiles sp
        ON u.id = sp.user_id
    WHERE u.id = ?
      AND u.role = 'student'
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$student = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

/* =========================
   UPDATE PROFILE
   ========================= */

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $semester = (int) ($_POST["semester"] ?? 0);

    /* ---------- VALIDATION ---------- */

    if ($name === "") {

        $error = "Name is required.";

    } elseif ($email === "") {

        $error = "Email is required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($course === "") {

        $error = "Course is required.";

    } elseif ($semester < 1 || $semester > 8) {

        $error = "Semester must be between 1 and 8.";

    } else {

        /* ---------- CHECK EMAIL ---------- */

        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
              AND id != ?
        ");

        $stmt->bind_param("si", $email, $student_id);
        $stmt->execute();

        $existing_user = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if ($existing_user) {

            $error = "This email address is already in use.";

        } else {

            /* ---------- UPDATE USERS ---------- */

            $stmt = $conn->prepare("
                UPDATE users
                SET name = ?, email = ?
                WHERE id = ?
                  AND role = 'student'
            ");

            $stmt->bind_param(
                "ssi",
                $name,
                $email,
                $student_id
            );

            $stmt->execute();

            $stmt->close();


            /* ---------- UPDATE STUDENT PROFILE ---------- */

            $stmt = $conn->prepare("
                UPDATE student_profiles
                SET course = ?, semester = ?
                WHERE user_id = ?
            ");

            $stmt->bind_param(
                "sii",
                $course,
                $semester,
                $student_id
            );

            $stmt->execute();

            $stmt->close();


            /* ---------- UPDATE SESSION NAME/EMAIL ---------- */

            $_SESSION["name"] = $name;
            $_SESSION["email"] = $email;

            $success = "Profile updated successfully.";


            /* ---------- REFRESH PROFILE DATA ---------- */

            $stmt = $conn->prepare("
                SELECT
                    u.id,
                    u.name,
                    u.email,
                    sp.student_id,
                    sp.course,
                    sp.semester
                FROM users u
                INNER JOIN student_profiles sp
                    ON u.id = sp.user_id
                WHERE u.id = ?
                  AND u.role = 'student'
            ");

            $stmt->bind_param("i", $student_id);
            $stmt->execute();

            $student = $stmt->get_result()->fetch_assoc();

            $stmt->close();
        }
    }
}

/* =========================
   CHANGE PASSWORD
   ========================= */

$password_success = "";
$password_error = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["change_password"])
) {

    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    /* ---------- VALIDATION ---------- */

    if ($current_password === "") {

        $password_error = "Current password is required.";

    } elseif ($new_password === "") {

        $password_error = "New password is required.";

    } elseif ($confirm_password === "") {

        $password_error = "Please confirm your new password.";

    } elseif ($new_password !== $confirm_password) {

        $password_error = "New passwords do not match.";

    } elseif (strlen($new_password) < 8) {

        $password_error =
            "New password must be at least 8 characters long.";

    } elseif (
        !preg_match('/[A-Z]/', $new_password)
        || !preg_match('/[a-z]/', $new_password)
        || !preg_match('/[0-9]/', $new_password)
    ) {

        $password_error =
            "Password must contain uppercase, lowercase, and a number.";

    } else {

        /* ---------- GET CURRENT HASH ---------- */

        $stmt = $conn->prepare("
            SELECT password
            FROM users
            WHERE id = ?
              AND role = 'student'
        ");

        $stmt->bind_param("i", $student_id);
        $stmt->execute();

        $password_data = $stmt->get_result()->fetch_assoc();

        $stmt->close();


        if (!$password_data) {

            $password_error = "Student account not found.";

        } elseif (
            !password_verify(
                $current_password,
                $password_data["password"]
            )
        ) {

            $password_error = "Current password is incorrect.";

        } else {

            /* ---------- HASH NEW PASSWORD ---------- */

            $new_password_hash = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );


            /* ---------- UPDATE PASSWORD ---------- */

            $stmt = $conn->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
                  AND role = 'student'
            ");

            $stmt->bind_param(
                "si",
                $new_password_hash,
                $student_id
            );

            $stmt->execute();

            $stmt->close();

            $password_success =
                "Password changed successfully.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">

</head>

<body>

    <div class="container">

    <?php if ($success !== ""): ?>

    <div class="success-message">
        <?= htmlspecialchars($success) ?>
    </div>

<?php endif; ?>


<?php if ($error !== ""): ?>

    <div class="error-message">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>

        <div class="dashboard-header">

            <div>

                <h1>My Profile</h1>

                <p>
                    View your personal and academic information.
                </p>

            </div>

        </div>


        <?php if (!$edit_mode): ?>

    <!-- =========================
         VIEW PROFILE
         ========================= -->

    <section class="dashboard-section">

        <div class="section-header">
            <h2>Personal Information</h2>
        </div>

        <div class="profile-grid">

            <div class="profile-field">
                <span>Full Name</span>
                <strong>
                    <?= htmlspecialchars($student["name"]) ?>
                </strong>
            </div>

            <div class="profile-field">
                <span>Email</span>
                <strong>
                    <?= htmlspecialchars($student["email"]) ?>
                </strong>
            </div>

        </div>

    </section>


    <section class="dashboard-section">

        <div class="section-header">
            <h2>Academic Information</h2>
        </div>

        <div class="profile-grid">

            <div class="profile-field">
                <span>Student ID</span>
                <strong>
                    <?= htmlspecialchars($student["student_id"]) ?>
                </strong>
            </div>

            <div class="profile-field">
                <span>Course</span>
                <strong>
                    <?= htmlspecialchars($student["course"]) ?>
                </strong>
            </div>

            <div class="profile-field">
                <span>Semester</span>
                <strong>
                    Semester <?= htmlspecialchars($student["semester"]) ?>
                </strong>
            </div>

        </div>

    </section>


    <div class="profile-actions">

    <a href="profile.php?edit=1" class="primary-button">
        Edit Profile
    </a>

    <a href="profile.php?password=1" class="secondary-button">
        Change Password
    </a>

    <a href="dashboard.php" class="secondary-button">
        ← Back to Dashboard
    </a>

</div>

<?php else: ?>

    <!-- =========================
         EDIT PROFILE
         ========================= -->

    <section class="dashboard-section">

        <div class="section-header">
            <h2>Edit Profile</h2>

            <a href="profile.php" class="view-all-link">
                Cancel
            </a>
        </div>


        <form method="POST" class="profile-form">

            <div class="profile-form-grid">

                <div class="profile-form-field">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars($student["name"]) ?>"
                        required
                    >

                </div>


                <div class="profile-form-field">

                    <label for="email">
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($student["email"]) ?>"
                        required
                    >

                </div>


                <div class="profile-form-field">

                    <label>
                        Student ID
                    </label>

                    <input
                        type="text"
                        value="<?= htmlspecialchars($student["student_id"]) ?>"
                        readonly
                    >

                    <small>
                        Student ID cannot be changed.
                    </small>

                </div>


                <div class="profile-form-field">

                    <label for="course">
                        Course
                    </label>

                    <input
                        type="text"
                        id="course"
                        name="course"
                        value="<?= htmlspecialchars($student["course"]) ?>"
                        required
                    >

                </div>


                <div class="profile-form-field">

                    <label for="semester">
                        Semester
                    </label>

                    <select
                        id="semester"
                        name="semester"
                        required
                    >

                        <?php for ($i = 1; $i <= 8; $i++): ?>

                            <option
                                value="<?= $i ?>"
                                <?= $student["semester"] == $i ? "selected" : "" ?>
                            >
                                Semester <?= $i ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>

            </div>


            <div class="profile-form-actions">

                <a
                    href="profile.php"
                    class="secondary-button"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="primary-button"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </section>

<?php endif; ?>

<?php if ($password_mode): ?>

    <!-- =========================
         CHANGE PASSWORD
         ========================= -->

    <section class="dashboard-section">

        <div class="section-header">

            <h2>Change Password</h2>

            <a href="profile.php" class="view-all-link">
                Cancel
            </a>

        </div>


        <?php if ($password_success !== ""): ?>

            <div class="success-message">
                <?= htmlspecialchars($password_success) ?>
            </div>

        <?php endif; ?>


        <?php if ($password_error !== ""): ?>

            <div class="error-message">
                <?= htmlspecialchars($password_error) ?>
            </div>

        <?php endif; ?>


        <form method="POST" class="password-form">

            <input
                type="hidden"
                name="change_password"
                value="1"
            >


            <div class="password-field">

                <label for="current_password">
                    Current Password
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword('current_password', this)"
                    >
                        Show
                    </button>

                </div>

            </div>


            <div class="password-field">

                <label for="new_password">
                    New Password
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword('new_password', this)"
                    >
                        Show
                    </button>

                </div>

                <small>
                    Minimum 8 characters, including uppercase,
                    lowercase, and a number.
                </small>

            </div>


            <div class="password-field">

                <label for="confirm_password">
                    Confirm New Password
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword('confirm_password', this)"
                    >
                        Show
                    </button>

                </div>

            </div>


            <div class="profile-form-actions">

                <a
                    href="profile.php"
                    class="secondary-button"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="primary-button"
                >
                    Change Password
                </button>

            </div>

        </form>

    </section>

<?php endif; ?>

    </div>

    <script>
function togglePassword(fieldId, button) {

    const field = document.getElementById(fieldId);

    if (field.type === "password") {

        field.type = "text";
        button.textContent = "Hide";

    } else {

        field.type = "password";
        button.textContent = "Show";

    }
}
</script>

</body>

</html>