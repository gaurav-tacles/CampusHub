<?php

session_start();

require_once "../config/db.php";

$errors = [];

$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    
    if ($email === "") {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if ($password === "") {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {

    $stmt = $conn->prepare(
        "SELECT id, name, email, password, role
         FROM users
         WHERE email = ?"
    );

    $stmt->bind_param(
        "s",
        $email
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        $hashedPassword = $user["password"];

        if (password_verify($password, $hashedPassword)) {

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["email"] = $user["email"];
        $_SESSION["role"] = $user["role"];

        header("Location: ../student/dashboard.php");
        exit;

        } else {

            $errors[] = "Incorrect password.";

        }

    } else {

        $errors[] = "No account found with this email.";

    }

}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <h1>CampusHub</h1>
    <h3>Login</h3>

    <?php if (!empty($errors)): ?>
    <div class="error-message">
        <?php foreach ($errors as $error): ?>
            <p>
                <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
    
    <form action="login.php" method="POST">

    <label>Email:</label>
    <input
        type="email"
        name="email"
        value="<?php echo htmlspecialchars($email); ?>"
        required
    >

    <br><br>

    <label>Password:</label>
    <input
        type="password"
        name="password"
        required
    >

    <br><br>

    <button type="submit">Submit</button>
    <a href="signup.php">Don't have an account? Let's Signup.</a>

</form>
</body>
</html>