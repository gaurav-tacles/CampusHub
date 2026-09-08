<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../config/db.php";

$errors = [];
$success = "";

$name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";


    // Name validation
    if ($name === "") {
        $errors[] = "Name is required.";
    } elseif (strlen($name) < 2) {
        $errors[] = "Name must contain at least 2 characters.";
    }


    // Email validation
    if ($email === "") {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }


    // Password validation
    if ($password === "") {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must contain at least 6 characters.";
    }


    // Confirm password validation
    if ($confirmPassword === "") {
        $errors[] = "Please confirm your password.";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }


    // Continue only if validation succeeds
    if (empty($errors)) {

        // Check whether email already exists
        $checkEmail = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $checkEmail->bind_param(
            "s",
            $email
        );

        $checkEmail->execute();

        $result = $checkEmail->get_result();


        if ($result->num_rows > 0) {

            $errors[] = "An account with this email already exists.";

        } else {

            // Securely hash the password
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            // Student is the default role
            $role = "student";


            // Insert new student
            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password, role)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssss",
                $name,
                $email,
                $hashedPassword,
                $role
            );


            if ($stmt->execute()) {

                $success = "Account created successfully! You can now log in.";

                // Clear form values
                $name = "";
                $email = "";

            } else {

                $errors[] = "Something went wrong. Please try again.";

            }

            $stmt->close();
        }

        $checkEmail->close();
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Student Signup - CampusHub</title>

    <link rel="stylesheet"
          href="../assets/style.css">

</head>


<body>

<div class="auth-container">

    <div class="auth-card">

        <h1>CampusHub</h1>

        <h2>Create Student Account</h2>

        <p>
            Create your account to access the student portal.
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


        <?php if ($success !== ""): ?>

            <div class="success-message">

                <?php echo htmlspecialchars($success); ?>

            </div>

        <?php endif; ?>


        <form method="POST"
              action="signup.php">

            <label>Name</label>

            <input
                type="text"
                name="name"
                value="<?php echo htmlspecialchars($name); ?>"
                required
            >


            <label>Email</label>

            <input
                type="email"
                name="email"
                value="<?php echo htmlspecialchars($email); ?>"
                required
            >


            <label>Password</label>

            <input
                type="password"
                name="password"
                required
            >


            <label>Confirm Password</label>

            <input
                type="password"
                name="confirm_password"
                required
            >


            <button type="submit">

                Create Account

            </button>

        </form>


        <p class="auth-link">

            Already have an account?

            <a href="login.php">

                Login

            </a>

        </p>

    </div>

</div>

</body>

</html>