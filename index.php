<?php
session_start();

if (isset($_SESSION["user_id"])) {

    if ($_SESSION["role"] === "student") {
        header("Location: student/dashboard.php");
        exit;
    }

    if ($_SESSION["role"] === "faculty") {
        header("Location: faculty/dashboard.php");
        exit;
    }

    if ($_SESSION["role"] === "admin") {
        header("Location: admin/dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CampusHub | College Portal</title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="landing-page">

    <main class="landing-container">

        <section class="landing-card">

            <div class="brand-icon">
                🎓
            </div>

            <p class="brand-name">CampusHub</p>

            <h1>
                Your Campus.<br>
                <span>One Platform.</span>
            </h1>

            <p class="landing-description">
                A simple and modern portal for students, faculty,
                and academic management.
            </p>

            <div class="landing-actions">

                <a href="auth/login.php" class="btn btn-primary">
                    Login
                    <span>→</span>
                </a>

                <a href="auth/signup.php" class="btn btn-secondary">
                    Create Student Account
                </a>

            </div>

            <div class="landing-footer">
                <span>Student</span>
                <span>•</span>
                <span>Faculty</span>
                <span>•</span>
                <span>Administration</span>
            </div>

        </section>

    </main>

</body>
</html>