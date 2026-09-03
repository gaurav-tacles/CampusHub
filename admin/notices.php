<?php

session_start();

require_once "../config/db.php";


// Login Protection
if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;
}


// Admin Role Protection
if ($_SESSION["role"] !== "admin") {

    header("Location: ../auth/login.php");
    exit;
}


$errors = [];
$success = "";

$title = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $message = trim($_POST["message"] ?? "");


    // Title validation
    if ($title === "") {

        $errors[] = "Notice title is required.";

    }


    // Message validation
    if ($message === "") {

        $errors[] = "Notice message is required.";

    }


    // Save notice
    if (empty($errors)) {

        $stmt = $conn->prepare(
            "INSERT INTO notices (title, message)
             VALUES (?, ?)"
        );

        $stmt->bind_param(
            "ss",
            $title,
            $message
        );


        if ($stmt->execute()) {

            $success = "Notice published successfully!";

            // Clear form after successful publishing
            $title = "";
            $message = "";

        } else {

            $errors[] = "Unable to publish notice.";

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

    <title>Manage Notices - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>

    <h1>CampusHub</h1>

    <h2>Manage Notices</h2>


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

            <p>
                <?php echo htmlspecialchars($success); ?>
            </p>

        </div>

    <?php endif; ?>


    <form action="notices.php" method="POST">

        <label>Notice Title:</label>

        <input
            type="text"
            name="title"
            value="<?php echo htmlspecialchars($title); ?>"
            required
        >

        <br><br>


        <label>Notice Message:</label>

        <textarea
            name="message"
            rows="6"
            required
        ><?php echo htmlspecialchars($message); ?></textarea>

        <br><br>


        <button type="submit">
            Publish Notice
        </button>

    </form>


    <br>

    <a href="dashboard.php">
        Back to Dashboard
    </a>

</body>

</html>