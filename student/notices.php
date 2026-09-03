<?php

session_start();

require_once "../config/db.php";


// Login Protection
if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;
}


// Student Role Protection
if ($_SESSION["role"] !== "student") {

    echo "Access denied.";
    exit;
}


// Get all notices
$stmt = $conn->prepare(
    "SELECT id, title, message, created_at
     FROM notices
     ORDER BY created_at DESC"
);

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Notices - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">

</head>

<body>

    <h1>CampusHub</h1>

    <h2>Notices</h2>

    <p>
        Welcome,
        <?php echo htmlspecialchars($_SESSION["name"]); ?>
    </p>


    <?php if ($result->num_rows > 0): ?>

        <?php while ($notice = $result->fetch_assoc()): ?>

            <div class="notice">

                <h3>
                    <?php echo htmlspecialchars($notice["title"]); ?>
                </h3>

                <p>
                    <?php echo nl2br(htmlspecialchars($notice["message"])); ?>
                </p>

                <small>
                    Posted:
                    <?php echo htmlspecialchars($notice["created_at"]); ?>
                </small>

            </div>

            <hr>

        <?php endwhile; ?>

    <?php else: ?>

        <p>No notices available.</p>

    <?php endif; ?>


    <br>

    <a href="dashboard.php">
        Back to Dashboard
    </a>

</body>

</html>

<?php

$stmt->close();

?>