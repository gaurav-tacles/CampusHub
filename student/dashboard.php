<?php

session_start();
require_once "../config/db.php";

$userId = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT
        users.name,
        users.email,
        student_profiles.student_id,
        student_profiles.course,
        student_profiles.semester
     FROM users
     LEFT JOIN student_profiles
        ON users.id = student_profiles.user_id
     WHERE users.id = ?"
);

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$result = $stmt->get_result();

$student = $result->fetch_assoc();

$stmt->close();

if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;

}

if ($_SESSION["role"] !== "student") {

    echo "Access denied.";
    exit;

}

/* =========================
   STUDENT INFORMATION
   ========================= */

$student_id = $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT
        u.name,
        u.email,
        sp.course,
        sp.semester
    FROM users u
    INNER JOIN student_profiles sp
        ON u.id = sp.user_id
    WHERE u.id = ?
      AND u.role = 'student'
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

/* =========================
   ATTENDANCE SUMMARY
   ========================= */

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'present') AS present,
        SUM(status = 'absent') AS absent
    FROM attendance
    WHERE student_id = ?
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$attendance = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_attendance = (int) $attendance["total"];
$present_attendance = (int) ($attendance["present"] ?? 0);
$absent_attendance = (int) ($attendance["absent"] ?? 0);

if ($total_attendance > 0) {
    $attendance_percentage =
        ($present_attendance / $total_attendance) * 100;
} else {
    $attendance_percentage = 0;
}

/* =========================
   ASSIGNMENT SUMMARY
   ========================= */

$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT a.id) AS total_assignments,
        COUNT(DISTINCT CASE
            WHEN sub.id IS NOT NULL THEN a.id
        END) AS submitted_assignments,
        COUNT(DISTINCT CASE
            WHEN sub.status = 'graded' THEN a.id
        END) AS graded_assignments
    FROM assignments a
    INNER JOIN faculty_subjects fs
        ON a.faculty_subject_id = fs.id
    LEFT JOIN assignment_submissions sub
        ON a.id = sub.assignment_id
        AND sub.student_id = ?
    WHERE fs.semester = ?
");

$stmt->bind_param("ii", $student_id, $student["semester"]);
$stmt->execute();

$assignment_summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_assignments_student =
    (int) $assignment_summary["total_assignments"];

$submitted_assignments =
    (int) $assignment_summary["submitted_assignments"];

$graded_assignments =
    (int) $assignment_summary["graded_assignments"];

$pending_assignments =
    $total_assignments_student - $submitted_assignments;

    /* =========================
   RESULTS SUMMARY
   ========================= */

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_results,
        COALESCE(SUM(marks), 0) AS total_marks,
        COALESCE(AVG(marks), 0) AS average_marks
    FROM results
    WHERE student_id = ?
      AND status = 'published'
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result_summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_published_results =
    (int) $result_summary["total_results"];

$total_marks_obtained =
    (float) $result_summary["total_marks"];

$average_marks =
    (float) $result_summary["average_marks"];

    /* =========================
   RECENT NOTICES
   ========================= */

$stmt = $conn->prepare("
    SELECT
        n.id,
        n.title,
        n.message,
        n.created_at,
        u.name AS posted_by,
        u.role
    FROM notices n
    INNER JOIN users u
        ON n.created_by = u.id
    ORDER BY n.created_at DESC
    LIMIT 5
");

$stmt->execute();

$recent_notices = $stmt->get_result();

$stmt->close();

/* =========================
   RECENT ASSIGNMENTS
   ========================= */

$stmt = $conn->prepare("
    SELECT
        a.id,
        a.title,
        a.due_date,
        s.subject_name,
        s.subject_code,
        u.name AS faculty_name,
        sub.status AS submission_status,
        sub.marks
    FROM assignments a

    INNER JOIN faculty_subjects fs
        ON a.faculty_subject_id = fs.id

    INNER JOIN subjects s
        ON fs.subject_id = s.id

    INNER JOIN users u
        ON fs.faculty_id = u.id

    LEFT JOIN assignment_submissions sub
        ON a.id = sub.assignment_id
        AND sub.student_id = ?

    WHERE fs.semester = ?

    ORDER BY a.due_date ASC

    LIMIT 5
");

$stmt->bind_param(
    "ii",
    $student_id,
    $student["semester"]
);

$stmt->execute();

$recent_assignments = $stmt->get_result();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <h1>CampusHub</h1>

<div class="dashboard-header">

    <div>
        <h1>
            Welcome, <?= htmlspecialchars($student["name"]) ?>
        </h1>

        <p>
            Here's your CampusHub academic overview.
        </p>
    </div>

</div>
<div class="student-academic-info">

    <div class="academic-info-card">
        <span>Course</span>
        <strong>
            <?= htmlspecialchars($student["course"]) ?>
        </strong>
    </div>

    <div class="academic-info-card">
        <span>Semester</span>
        <strong>
            <?= htmlspecialchars($student["semester"]) ?>
        </strong>
    </div>

    <div class="academic-info-card">
        <span>Email</span>
        <strong>
            <?= htmlspecialchars($student["email"]) ?>
        </strong>
    </div>

</div>
<br>

<section class="dashboard-section">

    <div class="section-header">
        <h2>Attendance Overview</h2>

        <a href="attendance.php" class="view-all-link">
            View Attendance
        </a>
    </div>

    <div class="student-attendance-overview">

        <div class="student-attendance-stat">
            <span>Total Classes</span>
            <strong><?= $total_attendance ?></strong>
        </div>

        <div class="student-attendance-stat">
            <span>Present</span>
            <strong><?= $present_attendance ?></strong>
        </div>

        <div class="student-attendance-stat">
            <span>Absent</span>
            <strong><?= $absent_attendance ?></strong>
        </div>

        <div class="student-attendance-stat">
            <span>Attendance</span>
            <strong>
                <?= number_format($attendance_percentage, 1) ?>%
            </strong>
        </div>

    </div>

</section>
<section class="dashboard-section">

    <div class="section-header">
        <h2>Assignment Overview</h2>

        <a href="assignments.php" class="view-all-link">
            View Assignments
        </a>
    </div>

    <div class="student-assignment-overview">

        <div class="student-assignment-stat">
            <span>Total Assignments</span>
            <strong><?= $total_assignments_student ?></strong>
        </div>

        <div class="student-assignment-stat">
            <span>Submitted</span>
            <strong><?= $submitted_assignments ?></strong>
        </div>

        <div class="student-assignment-stat">
            <span>Pending</span>
            <strong><?= $pending_assignments ?></strong>
        </div>

        <div class="student-assignment-stat">
            <span>Graded</span>
            <strong><?= $graded_assignments ?></strong>
        </div>

    </div>

</section>
<section class="dashboard-section">

    <div class="section-header">
        <h2>Results Overview</h2>

        <a href="results.php" class="view-all-link">
            View Results
        </a>
    </div>

    <div class="student-results-overview">

        <div class="student-result-stat">
            <span>Published Subjects</span>
            <strong><?= $total_published_results ?></strong>
        </div>

        <div class="student-result-stat">
            <span>Total Marks</span>
            <strong><?= number_format($total_marks_obtained, 2) ?></strong>
        </div>

        <div class="student-result-stat">
            <span>Average Marks</span>
            <strong><?= number_format($average_marks, 2) ?></strong>
        </div>

    </div>

</section>
<section class="dashboard-section">

    <div class="section-header">
        <h2>Recent Notices</h2>

        <a href="notices.php" class="view-all-link">
            View All Notices
        </a>
    </div>

    <div class="student-notices">

        <?php if ($recent_notices->num_rows > 0): ?>

            <?php while ($notice = $recent_notices->fetch_assoc()): ?>

                <div class="student-notice-card">

                    <div class="student-notice-content">

                        <h3>
                            <?= htmlspecialchars($notice["title"]) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars($notice["message"]) ?>
                        </p>

                    </div>

                    <div class="student-notice-meta">

                        <span>
                            Posted by
                            <?= htmlspecialchars($notice["posted_by"]) ?>
                            (<?= htmlspecialchars(ucfirst($notice["role"])) ?>)
                        </span>

                        <span>
                            <?= date(
                                "d M Y, h:i A",
                                strtotime($notice["created_at"])
                            ) ?>
                        </span>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="student-empty-state">
                No notices available.
            </div>

        <?php endif; ?>

    </div>

</section>
<section class="dashboard-section">

    <div class="section-header">
        <h2>Recent Assignments</h2>

        <a href="assignments.php" class="view-all-link">
            View All Assignments
        </a>
    </div>

    <div class="student-assignments">

        <?php if ($recent_assignments->num_rows > 0): ?>

            <?php while ($assignment = $recent_assignments->fetch_assoc()): ?>

                <?php
                if ($assignment["submission_status"] === "graded") {
                    $status_text = "Graded";
                } elseif ($assignment["submission_status"] === "submitted") {
                    $status_text = "Submitted";
                } else {
                    $status_text = "Pending";
                }
                ?>

                <div class="student-assignment-card">

                    <div class="student-assignment-main">

                        <h3>
                            <?= htmlspecialchars($assignment["title"]) ?>
                        </h3>

                        <p class="student-assignment-subject">
                            <?= htmlspecialchars($assignment["subject_code"]) ?>
                            —
                            <?= htmlspecialchars($assignment["subject_name"]) ?>
                        </p>

                        <p class="student-assignment-faculty">
                            Faculty:
                            <?= htmlspecialchars($assignment["faculty_name"]) ?>
                        </p>

                    </div>

                    <div class="student-assignment-info">

                        <div>
                            <span>Due Date</span>

                            <strong>
                                <?= date(
                                    "d M Y, h:i A",
                                    strtotime($assignment["due_date"])
                                ) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Status</span>

                            <strong class="assignment-status">
                                <?= $status_text ?>
                            </strong>
                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="student-empty-state">
                No assignments available.
            </div>

        <?php endif; ?>

    </div>

</section>

<div class="dashboard-links">

    <a href="profile.php">
        My Profile
    </a>

    <a href="attendance.php">
        Attendance
    </a>
    

    <a href="results.php">
        Results
    </a>

    <a href="notices.php">
        Notices
    </a>
    
    <a href="assignments.php">
        Assignments
    </a>

    <a href="../auth/logout.php">
        Logout
    </a>

</div>

</body>
</html>