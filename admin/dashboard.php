<?php
session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

/* =========================
   DASHBOARD STATISTICS
   ========================= */

// Total Students
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'student'
");
$total_students = $result->fetch_assoc()["total"];

// Total Faculty
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'faculty'
");
$total_faculty = $result->fetch_assoc()["total"];

// Total Subjects
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM subjects
");
$total_subjects = $result->fetch_assoc()["total"];

// Total Notices
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM notices
");
$total_notices = $result->fetch_assoc()["total"];

// Total Assignments
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM assignments
");
$total_assignments = $result->fetch_assoc()["total"];

// Total Submissions
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM assignment_submissions
");
$total_submissions = $result->fetch_assoc()["total"];

// Published Results
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM results
    WHERE status = 'published'
");
$published_results = $result->fetch_assoc()["total"];


/* =========================
   ASSIGNMENT STATISTICS
   ========================= */

// Graded Submissions
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM assignment_submissions
    WHERE status = 'graded'
");
$graded_submissions = $result->fetch_assoc()["total"];

// Pending Reviews
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM assignment_submissions
    WHERE status = 'submitted'
");
$pending_submissions = $result->fetch_assoc()["total"];

/* =========================
   RESULT STATISTICS
   ========================= */

// Draft Results
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM results
    WHERE status = 'draft'
");
$draft_results = $result->fetch_assoc()["total"];

// Submitted Results
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM results
    WHERE status = 'submitted'
");
$submitted_results = $result->fetch_assoc()["total"];

// Approved Results
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM results
    WHERE status = 'approved'
");
$approved_results = $result->fetch_assoc()["total"];

// Rejected Results
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM results
    WHERE status = 'rejected'
");
$rejected_results = $result->fetch_assoc()["total"];

// Published Results
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM results
    WHERE status = 'published'
");
$published_results = $result->fetch_assoc()["total"];

/* =========================
   ATTENDANCE STATISTICS
   ========================= */

// Total Attendance Records
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM attendance
");
$total_attendance = $result->fetch_assoc()["total"];

// Present Records
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM attendance
    WHERE status = 'present'
");
$present_attendance = $result->fetch_assoc()["total"];

// Absent Records
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM attendance
    WHERE status = 'absent'
");
$absent_attendance = $result->fetch_assoc()["total"];

// Overall Attendance Percentage
if ($total_attendance > 0) {
    $attendance_percentage =
        ($present_attendance / $total_attendance) * 100;
} else {
    $attendance_percentage = 0;
}


/* =========================
   RECENT NOTICES
   ========================= */

$recent_notices = $conn->query("
    SELECT
        n.id,
        n.title,
        n.created_at,
        u.name AS posted_by,
        u.role
    FROM notices n
    INNER JOIN users u ON n.created_by = u.id
    ORDER BY n.created_at DESC
    LIMIT 5
");

/* =========================
   RECENT ASSIGNMENTS
   ========================= */

$recent_assignments = $conn->query("
    SELECT
        a.id,
        a.title,
        a.due_date,
        a.created_at,
        s.subject_code,
        s.subject_name,
        u.name AS faculty_name
    FROM assignments a
    INNER JOIN faculty_subjects fs
        ON a.faculty_subject_id = fs.id
    INNER JOIN subjects s
        ON fs.subject_id = s.id
    INNER JOIN users u
        ON fs.faculty_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
");
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard - CampusHub</title>

    <link rel="stylesheet" href="../assets/style.css">

</head>

<body>

    <div class="dashboard-header">
    <div>
        <h1>Admin Dashboard</h1>
        <p>
            Welcome back, <?= htmlspecialchars($_SESSION["name"]) ?>.
            Here's what's happening in CampusHub.
        </p>
    </div>
</div>


    <br>

    <div class="dashboard-stats">

    <div class="stat-card">
        <h3>Total Students</h3>
        <p><?= $total_students ?></p>
    </div>

    <div class="stat-card">
        <h3>Total Faculty</h3>
        <p><?= $total_faculty ?></p>
    </div>

    <div class="stat-card">
        <h3>Total Subjects</h3>
        <p><?= $total_subjects ?></p>
    </div>

    <div class="stat-card">
        <h3>Total Notices</h3>
        <p><?= $total_notices ?></p>
    </div>

    <div class="stat-card">
        <h3>Total Assignments</h3>
        <p><?= $total_assignments ?></p>
    </div>

    <div class="stat-card">
        <h3>Total Submissions</h3>
        <p><?= $total_submissions ?></p>
    </div>

    <div class="stat-card">
        <h3>Published Results</h3>
        <p><?= $published_results ?></p>
    </div>

</div>

<section class="dashboard-section">
    <h2>Quick Actions</h2>

    <div class="quick-actions">

        <a href="notices.php" class="quick-action-card">
            <h3>Manage Notices</h3>
            <p>Create, edit and delete notices</p>
        </a>

        <a href="results.php" class="quick-action-card">
            <h3>Manage Results</h3>
            <p>Review, approve and publish results</p>
        </a>

        <a href="assignments.php" class="quick-action-card">
            <h3>Faculty Subjects</h3>
            <p>Assign subjects to faculty</p>
        </a>

        <a href="academic_assignments.php" class="quick-action-card">
            <h3>Academic Assignments</h3>
            <p>Monitor assignments and submissions</p>
        </a>

    </div>
</section>

<section class="dashboard-section">

    <h2>Recent Notices</h2>

    <div class="recent-notices">

        <?php if ($recent_notices->num_rows > 0): ?>

            <?php while ($notice = $recent_notices->fetch_assoc()): ?>

                <div class="notice-preview">

                    <div>
                        <h3>
                            <?= htmlspecialchars($notice["title"]) ?>
                        </h3>

                        <p>
                            Posted by
                            <strong><?= htmlspecialchars($notice["posted_by"]) ?></strong>
                            (<?= htmlspecialchars(ucfirst($notice["role"])) ?>)
                        </p>
                    </div>

                    <span>
                        <?= date("d M Y", strtotime($notice["created_at"])) ?>
                    </span>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-message">No notices available.</p>

        <?php endif; ?>

    </div>

</section>

<section class="dashboard-section">

    <div class="section-header">
        <h2>Assignment Overview</h2>

        <a href="academic_assignments.php" class="view-all-link">
            View All
        </a>
    </div>

    <div class="assignment-overview">

        <div class="assignment-stat">
            <span>Total Assignments</span>
            <strong><?= $total_assignments ?></strong>
        </div>

        <div class="assignment-stat">
            <span>Total Submissions</span>
            <strong><?= $total_submissions ?></strong>
        </div>

        <div class="assignment-stat">
            <span>Graded</span>
            <strong><?= $graded_submissions ?></strong>
        </div>

        <div class="assignment-stat">
            <span>Pending Review</span>
            <strong><?= $pending_submissions ?></strong>
        </div>

    </div>

</section>

<section class="dashboard-section">

    <div class="section-header">
        <h2>Results Overview</h2>

        <a href="results.php" class="view-all-link">
            Manage Results
        </a>
    </div>

    <div class="results-overview">

        <div class="result-stat">
            <span>Draft</span>
            <strong><?= $draft_results ?></strong>
        </div>

        <div class="result-stat">
            <span>Submitted</span>
            <strong><?= $submitted_results ?></strong>
        </div>

        <div class="result-stat">
            <span>Approved</span>
            <strong><?= $approved_results ?></strong>
        </div>

        <div class="result-stat">
            <span>Rejected</span>
            <strong><?= $rejected_results ?></strong>
        </div>

        <div class="result-stat">
            <span>Published</span>
            <strong><?= $published_results ?></strong>
        </div>

    </div>

</section>

<section class="dashboard-section">

    <div class="section-header">
        <h2>Attendance Overview</h2>

        <a href="../faculty/attendance.php" class="view-all-link">
            Attendance Management
        </a>
    </div>

    <div class="attendance-overview">

        <div class="attendance-stat">
            <span>Total Records</span>
            <strong><?= $total_attendance ?></strong>
        </div>

        <div class="attendance-stat">
            <span>Present</span>
            <strong><?= $present_attendance ?></strong>
        </div>

        <div class="attendance-stat">
            <span>Absent</span>
            <strong><?= $absent_attendance ?></strong>
        </div>

        <div class="attendance-stat">
            <span>Overall Attendance</span>
            <strong><?= number_format($attendance_percentage, 1) ?>%</strong>
        </div>

    </div>

</section>

<section class="dashboard-section">

    <div class="section-header">
        <h2>Recent Assignments</h2>

        <a href="academic_assignments.php" class="view-all-link">
            View All
        </a>
    </div>

    <div class="recent-assignments">

        <?php if ($recent_assignments->num_rows > 0): ?>

            <?php while ($assignment = $recent_assignments->fetch_assoc()): ?>

                <div class="assignment-preview">

                    <div class="assignment-info">

                        <h3>
                            <?= htmlspecialchars($assignment["title"]) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars($assignment["subject_code"]) ?>
                            -
                            <?= htmlspecialchars($assignment["subject_name"]) ?>
                        </p>

                        <small>
                            Posted by
                            <strong>
                                <?= htmlspecialchars($assignment["faculty_name"]) ?>
                            </strong>
                        </small>

                    </div>

                    <div class="assignment-due">

                        <span>Due</span>

                        <strong>
                            <?= date("d M Y", strtotime($assignment["due_date"])) ?>
                        </strong>

                        <small>
                            <?= date("h:i A", strtotime($assignment["due_date"])) ?>
                        </small>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-message">
                No assignments available.
            </p>

        <?php endif; ?>

    </div>

</section>
    <a href="../auth/logout.php">
        Logout
    </a>


</body>

</html>