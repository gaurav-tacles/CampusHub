<?php
session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION["role"] !== "faculty") {
    echo "Access denied.";
    exit;
}

$noticeId = (int) ($_GET["id"] ?? 0);
$userId = $_SESSION["user_id"];

if ($noticeId <= 0) {
    echo "Invalid notice.";
    exit;
}

/*
 * Get the notice only if it belongs to
 * the currently logged-in faculty member.
 */
$stmt = $conn->prepare(
    "SELECT id, title, message
     FROM notices
     WHERE id = ?
     AND created_by = ?"
);

$stmt->bind_param("ii", $noticeId, $userId);
$stmt->execute();

$result = $stmt->get_result();
$notice = $result->fetch_assoc();

$stmt->close();

if (!$notice) {
    echo "You cannot edit this notice.";
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $noticeMessage = trim($_POST["message"] ?? "");

    if ($title === "" || $noticeMessage === "") {

        $message = "Title and message are required.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE notices
             SET title = ?, message = ?
             WHERE id = ?
             AND created_by = ?"
        );

        $stmt->bind_param(
            "ssii",
            $title,
            $noticeMessage,
            $noticeId,
            $userId
        );

        if ($stmt->execute()) {

            header("Location: notices.php?updated=1");
            exit;

        } else {

            $message = "Notice could not be updated: " . $stmt->error;
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Notice - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">

</head>

<body>

<h1>CampusHub</h1>

<h2>Edit Notice</h2>

<?php if ($message !== ""): ?>

    <p>
        <?php echo htmlspecialchars($message); ?>
    </p>

<?php endif; ?>

<form method="POST">

    <label>Notice Title</label><br>

    <input
        type="text"
        name="title"
        value="<?php echo htmlspecialchars($notice["title"]); ?>"
        required
    >

    <br><br>

    <label>Notice Message</label><br>

    <textarea
        name="message"
        rows="6"
        required
    ><?php echo htmlspecialchars($notice["message"]); ?></textarea>

    <br><br>

    <button type="submit">
        Update Notice
    </button>

</form>

<br>

<a href="notices.php">Back to Notices</a>

</body>

</html>