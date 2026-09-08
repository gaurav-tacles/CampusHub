<?php

session_start();

require_once "../config/db.php";

// Only students can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| MARK ALL NOTIFICATIONS AS READ
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_all_read"])) {

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = TRUE
        WHERE user_id = ?
          AND is_read = FALSE
    ");

    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->close();

    header("Location: notifications.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD STUDENT NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        message,
        type,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$notifications = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Notifications - CampusHub</title>

    <link rel="stylesheet"
          href="../assets/style.css">

</head>

<body>

<div class="container">

    <div class="page-header">

        <div>
            <h1>Notifications</h1>

            <p>
                Stay updated with your CampusHub activities.
            </p>
        </div>

        <div>

            <a href="dashboard.php"
               class="btn">
                Back to Dashboard
            </a>

        </div>

    </div>


    <div class="notification-actions">

        <form method="POST">

            <button
                type="submit"
                name="mark_all_read"
                class="btn">

                Mark All as Read

            </button>

        </form>

    </div>


    <div class="notifications-list">

        <?php if ($notifications->num_rows === 0): ?>

            <div class="empty-state">

                <h3>No Notifications</h3>

                <p>
                    You don't have any notifications yet.
                </p>

            </div>

        <?php else: ?>

            <?php while ($notification = $notifications->fetch_assoc()): ?>

                <div class="notification-card
                    <?php echo $notification["is_read"] ? "read" : "unread"; ?>">

                    <div class="notification-content">

                        <div class="notification-title-row">

                            <h3>
                                <?php
                                echo htmlspecialchars(
                                    $notification["title"]
                                );
                                ?>
                            </h3>

                            <?php if (!$notification["is_read"]): ?>

                                <span class="unread-badge">
                                    New
                                </span>

                            <?php endif; ?>

                        </div>


                        <p>

                            <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $notification["message"]
                                )
                            );
                            ?>

                        </p>


                        <small>

                            <?php
                            echo htmlspecialchars(
                                $notification["created_at"]
                            );
                            ?>

                        </small>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php endif; ?>

    </div>

</div>

</body>

</html>