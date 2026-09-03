<?php

session_start();

require_once "../config/db.php";


// Login Protection
if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;
}


// Faculty Role Protection
if ($_SESSION["role"] !== "faculty") {

    echo "Access denied.";
    exit;
}

$search = trim($_GET["search"] ?? "");

// Get all students
if ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT
            u.name,
            u.email,
            sp.student_id,
            sp.course,
            sp.semester
         FROM users u
         INNER JOIN student_profiles sp
            ON u.id = sp.user_id
         WHERE u.role = 'student'
         AND (
             u.name LIKE ?
             OR sp.student_id LIKE ?
         )
         ORDER BY sp.student_id"
    );

    $searchValue = "%" . $search . "%";

    $stmt->bind_param(
        "ss",
        $searchValue,
        $searchValue
    );

} else {

    $stmt = $conn->prepare(
        "SELECT
            u.name,
            u.email,
            sp.student_id,
            sp.course,
            sp.semester
         FROM users u
         INNER JOIN student_profiles sp
            ON u.id = sp.user_id
         WHERE u.role = 'student'
         ORDER BY sp.student_id"
    );
}

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

    <title>Students - CampusHub</title>

    <link
        rel="stylesheet"
        href="../assets/style.css"
    >

</head>

<body>

    <h1>CampusHub</h1>

    <h2>Students</h2>

        <form method="GET">

        <input
            type="search"
            name="search"
            placeholder="Search by name or Student ID"
            value="<?php echo htmlspecialchars($search); ?>"
        >

        <button type="submit">
            Search
        </button>

        <a href="students.php">
            Clear
        </a>

    </form>

    <br>

    <table border="1" cellpadding="10">

        <tr>
            <th>Student ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Course</th>
            <th>Semester</th>
        </tr>

        <?php if ($result->num_rows > 0): ?>

            <?php while ($student = $result->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?php echo htmlspecialchars($student["student_id"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($student["name"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($student["email"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($student["course"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($student["semester"]); ?>
                    </td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="5">
                    No students found.
                </td>
            </tr>

        <?php endif; ?>

    </table>

    <br>

    <a href="dashboard.php">
        Back to Dashboard
    </a>

</body>

</html>