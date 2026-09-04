<?php
session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if (isset($_GET["delete"])) {

    $noticeId = (int) $_GET["delete"];
    $userId = $_SESSION["user_id"];

    $stmt = $conn->prepare(
        "DELETE FROM notices
         WHERE id = ?
         AND created_by = ?"
    );

    $stmt->bind_param(
        "ii",
        $noticeId,
        $userId
    );

    if ($stmt->execute()) {

        if ($stmt->affected_rows > 0) {
            header("Location: notices.php?deleted=1");
            exit;
        } else {
            header("Location: notices.php?delete_error=1");
            exit;
        }

    } else {

        header("Location: notices.php?delete_error=1");
        exit;
    }

    $stmt->close();
}

if ($_SESSION["role"] !== "faculty") {
    echo "Access denied.";
    exit;
}

$message = "";
$messageType = "";

if (isset($_GET["deleted"]) && $_GET["deleted"] === "1") {
    $message = "Notice deleted successfully.";
    $messageType = "success";
}

if (isset($_GET["delete_error"]) && $_GET["delete_error"] === "1") {
    $message = "Notice could not be deleted. You can only delete your own notices.";
    $messageType = "error";
}

if (isset($_GET["updated"]) && $_GET["updated"] === "1") {
    $message = "Notice updated successfully.";
    $messageType = "success";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $noticeMessage = trim($_POST["message"] ?? "");
    $createdBy = $_SESSION["user_id"];

    if ($title === "" || $noticeMessage === "") {

        $message = "Please enter both title and message.";
        $messageType = "error";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO notices (title, message, created_by)
             VALUES (?, ?, ?)"
        );

        $stmt->bind_param(
            "ssi",
            $title,
            $noticeMessage,
            $createdBy
        );

        if ($stmt->execute()) {

            /*
             * Redirect after successful submission.
             * This prevents the form from being submitted again
             * when the page is refreshed.
             */
            header("Location: notices.php?success=1");
            exit;

        } else {

            $message = "Notice could not be posted: " . $stmt->error;
            $messageType = "error";
        }

        $stmt->close();
    }
}

if (isset($_GET["success"]) && $_GET["success"] === "1") {
    $message = "Notice posted successfully.";
    $messageType = "success";

    if (isset($_GET["deleted"]) && $_GET["deleted"] === "1") {
    $message = "Faculty assignment removed successfully.";
    $messageType = "success";
}

if (isset($_GET["delete_error"]) && $_GET["delete_error"] === "1") {
    $message = "Assignment could not be removed.";
    $messageType = "error";
}

}



$stmt = $conn->prepare(
    "SELECT
        n.id,
        n.title,
        n.message,
        n.created_at,
        u.id AS creator_id,
        u.name AS creator_name,
        u.role AS creator_role
     FROM notices n
     INNER JOIN users u
        ON n.created_by = u.id
     ORDER BY n.created_at DESC"
);

$stmt->execute();

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Faculty Notices - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">

</head>

<body>

<h1>CampusHub</h1>

<h2>Faculty Notices</h2>

<p>
    Welcome,
    <?php echo htmlspecialchars($_SESSION["name"]); ?>
</p>

<?php if ($message !== ""): ?>

    <p>
        <?php echo htmlspecialchars($message); ?>
    </p>

<?php endif; ?>

<h3>Post Notice</h3>

<form method="POST">

    <label>Notice Title</label><br>

    <input
        type="text"
        name="title"
        required
    >

    <br><br>

    <label>Notice Message</label><br>

    <textarea
        name="message"
        rows="5"
        required
    ></textarea>

    <br><br>

    <button type="submit">
        Post Notice
    </button>

</form>

<br>

<h3>Existing Notices</h3>

<?php if ($result->num_rows > 0): ?>

    <?php while ($notice = $result->fetch_assoc()): ?>

        <div>

            <h4>
                <?php echo htmlspecialchars($notice["title"]); ?>
            </h4>

            <p>
                <?php
                echo nl2br(
                    htmlspecialchars($notice["message"])
                );
                ?>
            </p>

            <p>
                Posted by:
                <?php echo htmlspecialchars($notice["creator_name"]); ?>
                (<?php echo htmlspecialchars($notice["creator_role"]); ?>)
            </p>

            <p>
                Date:
                <?php
                echo date(
                    "d-m-Y H:i",
                    strtotime($notice["created_at"])
                );
                ?>
            </p>

            <?php if ((int)$notice["creator_id"] === (int)$_SESSION["user_id"]): ?>

                <a href="edit_notice.php?id=<?php echo $notice["id"]; ?>">
                    Edit
                </a>

                <a href="notices.php?delete=<?php echo $notice["id"]; ?>">
                    Delete
                </a>

            <?php endif; ?>

            <hr>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <p>No notices available.</p>

<?php endif; ?>

<a href="dashboard.php">Back to Dashboard</a>

</body>

</html>