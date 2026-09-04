<?php

session_start();

require_once "../config/db.php";

// Only admin can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Approve / Reject Result Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $faculty_subject_id = intval($_POST["faculty_subject_id"] ?? 0);
    $action = $_POST["action"] ?? "";
    $admin_comment = trim($_POST["admin_comment"] ?? "");

    /*
    |--------------------------------------------------------------------------
    | Validate Action
    |--------------------------------------------------------------------------
    */

    if (!in_array($action, ["approve", "reject", "publish"])) {

        $error = "Invalid action.";

    } elseif ($faculty_subject_id <= 0) {

        $error = "Invalid subject.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Verify Subject
        |--------------------------------------------------------------------------
        */

        $verify_sql = "
            SELECT
                fs.id,
                fs.semester,
                s.subject_code,
                s.subject_name,
                u.name AS faculty_name
            FROM faculty_subjects fs

            INNER JOIN subjects s
                ON fs.subject_id = s.id

            INNER JOIN users u
                ON fs.faculty_id = u.id

            WHERE fs.id = ?
        ";

        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("i", $faculty_subject_id);
        $verify_stmt->execute();

        $verify_result = $verify_stmt->get_result();

        if ($verify_result->num_rows === 0) {

            $error = "Subject assignment not found.";

        } else {

            $subject = $verify_result->fetch_assoc();


            /*
            |--------------------------------------------------------------------------
            | Make sure results are actually submitted
            |--------------------------------------------------------------------------
            */

            $status_sql = "
                SELECT COUNT(*) AS total,
                       SUM(status = 'submitted') AS submitted_count
                FROM results
                WHERE faculty_subject_id = ?
            ";

            $status_stmt = $conn->prepare($status_sql);
            $status_stmt->bind_param("i", $faculty_subject_id);
            $status_stmt->execute();

            $status_data = $status_stmt
                ->get_result()
                ->fetch_assoc();

            $total_results = (int)$status_data["total"];
            $submitted_count = (int)$status_data["submitted_count"];


            if ($total_results === 0) {

                $error = "No results found for this subject.";

            } elseif (
                in_array($action, ["approve", "reject"])
                && $submitted_count !== $total_results
            ) {

                $error = "All results must be submitted before Admin can review them.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | APPROVE
                |--------------------------------------------------------------------------
                */

                if ($action === "approve") {

                    $approve_sql = "
                        UPDATE results
                        SET
                            status = 'approved',
                            admin_comment = ?,
                            reviewed_at = CURRENT_TIMESTAMP
                        WHERE faculty_subject_id = ?
                        AND status = 'submitted'
                    ";

                    $approve_stmt = $conn->prepare($approve_sql);
                    $approve_stmt->bind_param(
                        "si",
                        $admin_comment,
                        $faculty_subject_id
                    );

                    if ($approve_stmt->execute()) {

                        $message =
                            "Results approved successfully. They are now ready for publishing.";

                    } else {

                        $error = "Failed to approve results.";
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | REJECT
                |--------------------------------------------------------------------------
                */

                elseif ($action === "reject") {

                    if ($admin_comment === "") {

                        $error =
                            "Please provide a reason when rejecting results.";

                    } else {

                        $reject_sql = "
                            UPDATE results
                            SET
                                status = 'rejected',
                                admin_comment = ?,
                                reviewed_at = CURRENT_TIMESTAMP
                            WHERE faculty_subject_id = ?
                            AND status = 'submitted'
                        ";

                        $reject_stmt = $conn->prepare($reject_sql);
                        $reject_stmt->bind_param(
                            "si",
                            $admin_comment,
                            $faculty_subject_id
                        );

                        if ($reject_stmt->execute()) {

                            $message =
                                "Results rejected and returned to faculty.";

                        } else {

                            $error = "Failed to reject results.";
                        }
                    }
                               }

                /*
                |--------------------------------------------------------------------------
                | PUBLISH
                |--------------------------------------------------------------------------
                */

                elseif ($action === "publish") {

                    /*
                    |--------------------------------------------------------------------------
                    | Make sure all results are approved
                    |--------------------------------------------------------------------------
                    */

                    $publish_status_sql = "
                        SELECT
                            COUNT(*) AS total,
                            SUM(status = 'approved') AS approved_count
                        FROM results
                        WHERE faculty_subject_id = ?
                    ";

                    $publish_status_stmt = $conn->prepare($publish_status_sql);
                    $publish_status_stmt->bind_param(
                        "i",
                        $faculty_subject_id
                    );
                    $publish_status_stmt->execute();

                    $publish_status_data = $publish_status_stmt
                        ->get_result()
                        ->fetch_assoc();

                    $publish_total = (int)$publish_status_data["total"];
                    $publish_approved = (int)$publish_status_data["approved_count"];

                    /*
                    |--------------------------------------------------------------------------
                    | Publish only when every result is approved
                    |--------------------------------------------------------------------------
                    */

                    if ($publish_total === 0) {

                        $error = "No results found for this subject.";

                    } elseif ($publish_approved !== $publish_total) {

                        $error = "All results must be approved before publishing.";

                    } else {

                        $publish_sql = "
                            UPDATE results
                            SET
                                status = 'published',
                                published_at = CURRENT_TIMESTAMP
                            WHERE faculty_subject_id = ?
                            AND status = 'approved'
                        ";

                        $publish_stmt = $conn->prepare($publish_sql);

                        $publish_stmt->bind_param(
                            "i",
                            $faculty_subject_id
                        );

                        if ($publish_stmt->execute()) {

                            $message =
                                "Results published successfully. Students can now view them.";

                        } else {

                            $error = "Failed to publish results.";

                        }

                        $publish_stmt->close();
                    }

                    $publish_status_stmt->close();
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Submitted / Reviewed Result Subjects
|--------------------------------------------------------------------------
*/

$subjects_sql = "
    SELECT
        fs.id AS faculty_subject_id,
        fs.semester,
        s.subject_code,
        s.subject_name,
        u.name AS faculty_name,

        COUNT(r.id) AS total_results,

        SUM(r.status = 'submitted') AS submitted_count,
        SUM(r.status = 'approved') AS approved_count,
        SUM(r.status = 'rejected') AS rejected_count,
        SUM(r.status = 'published') AS published_count

    FROM faculty_subjects fs

    INNER JOIN subjects s
        ON fs.subject_id = s.id

    INNER JOIN users u
        ON fs.faculty_id = u.id

    LEFT JOIN results r
        ON r.faculty_subject_id = fs.id

    GROUP BY
        fs.id,
        fs.semester,
        s.subject_code,
        s.subject_name,
        u.name

    HAVING total_results > 0

    ORDER BY fs.semester, s.subject_name
";

$subjects_result = $conn->query($subjects_sql);


/*
|--------------------------------------------------------------------------
| Selected Subject
|--------------------------------------------------------------------------
*/

$selected_subject_id =
    isset($_GET["faculty_subject_id"])
    ? intval($_GET["faculty_subject_id"])
    : 0;

$selected_subject = null;
$results_result = null;


/*
|--------------------------------------------------------------------------
| Load Selected Results
|--------------------------------------------------------------------------
*/

if ($selected_subject_id > 0) {

    $selected_sql = "
        SELECT
            fs.id AS faculty_subject_id,
            fs.semester,
            s.subject_code,
            s.subject_name,
            u.name AS faculty_name
        FROM faculty_subjects fs

        INNER JOIN subjects s
            ON fs.subject_id = s.id

        INNER JOIN users u
            ON fs.faculty_id = u.id

        WHERE fs.id = ?
    ";

    $selected_stmt = $conn->prepare($selected_sql);
    $selected_stmt->bind_param("i", $selected_subject_id);
    $selected_stmt->execute();

    $selected_data = $selected_stmt->get_result();

    if ($selected_data->num_rows > 0) {

        $selected_subject = $selected_data->fetch_assoc();


        /*
        |--------------------------------------------------------------------------
        | Load Student Results
        |--------------------------------------------------------------------------
        */

        $results_sql = "
            SELECT
                r.id AS result_id,
                r.marks,
                r.status,
                r.faculty_comment,
                r.admin_comment,
                r.submitted_at,
                r.reviewed_at,
                r.published_at,

                u.id AS user_id,
                u.name,
                u.email,

                sp.student_id AS student_number

            FROM results r

            INNER JOIN users u
                ON r.student_id = u.id

            INNER JOIN student_profiles sp
                ON u.id = sp.user_id

            WHERE r.faculty_subject_id = ?

            ORDER BY sp.student_id
        ";

        $results_stmt = $conn->prepare($results_sql);
        $results_stmt->bind_param("i", $selected_subject_id);
        $results_stmt->execute();

        $results_result = $results_stmt->get_result();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Review Results - Admin - CampusHub</title>

    <link rel="stylesheet"
          href="../assets/style.css">

</head>

<body>

<div class="container">

    <h1>Admin Result Review</h1>

    <p>
        Review result submissions from faculty.
    </p>


    <!-- Messages -->

    <?php if ($message): ?>

        <p style="color: green;">
            <?= htmlspecialchars($message) ?>
        </p>

    <?php endif; ?>


    <?php if ($error): ?>

        <p style="color: red;">
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>


    <!-- Subject Selection -->

    <form method="GET">

        <label for="faculty_subject_id">
            Select Result Submission:
        </label>

        <select
            name="faculty_subject_id"
            id="faculty_subject_id"
            required
        >

            <option value="">
                -- Select Subject --
            </option>

            <?php while ($subject = $subjects_result->fetch_assoc()): ?>

                <option
                    value="<?= $subject["faculty_subject_id"] ?>"
                    <?= ($selected_subject_id == $subject["faculty_subject_id"]) ? "selected" : "" ?>
                >

                    Semester <?= htmlspecialchars($subject["semester"]) ?>
                    -
                    <?= htmlspecialchars($subject["subject_code"]) ?>
                    -
                    <?= htmlspecialchars($subject["subject_name"]) ?>
                    -
                    Faculty:
                    <?= htmlspecialchars($subject["faculty_name"]) ?>

                </option>

            <?php endwhile; ?>

        </select>

        <button type="submit">
            Review
        </button>

    </form>


    <?php if ($selected_subject && $results_result): ?>

        <hr>

        <h2>
            <?= htmlspecialchars($selected_subject["subject_name"]) ?>
        </h2>

        <p>

            <strong>Subject Code:</strong>
            <?= htmlspecialchars($selected_subject["subject_code"]) ?>

            |

            <strong>Semester:</strong>
            <?= htmlspecialchars($selected_subject["semester"]) ?>

            |

            <strong>Faculty:</strong>
            <?= htmlspecialchars($selected_subject["faculty_name"]) ?>

        </p>


        <!-- Results Table -->

        <table border="1"
               cellpadding="10"
               cellspacing="0">

            <thead>

                <tr>

                    <th>Student ID</th>

                    <th>Name</th>

                    <th>Email</th>

                    <th>Marks</th>

                    <th>Status</th>

                    <th>Faculty Comment</th>

                    <th>Admin Comment</th>

                </tr>

            </thead>

            <tbody>

            <?php

            $has_submitted = false;
            $has_approved = false;
            $has_rejected = false;

            while ($result = $results_result->fetch_assoc()):

                if ($result["status"] === "submitted") {
                    $has_submitted = true;
                }

                if ($result["status"] === "approved") {
                    $has_approved = true;
                }

                if ($result["status"] === "rejected") {
                    $has_rejected = true;
                }

            ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($result["student_number"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($result["name"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($result["email"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($result["marks"]) ?>
                    </td>

                    <td>

                        <?php if ($result["status"] === "submitted"): ?>

                            <strong>
                                Submitted
                            </strong>

                        <?php elseif ($result["status"] === "approved"): ?>

                            Approved

                        <?php elseif ($result["status"] === "rejected"): ?>

                            <span style="color: red;">
                                Rejected
                            </span>

                        <?php elseif ($result["status"] === "published"): ?>

                            Published

                        <?php else: ?>

                            <?= htmlspecialchars($result["status"]) ?>

                        <?php endif; ?>

                    </td>

                    <td>

                        <?php if ($result["faculty_comment"]): ?>

                            <?= nl2br(
                                htmlspecialchars($result["faculty_comment"])
                            ) ?>

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </td>

                    <td>

                        <?php if ($result["admin_comment"]): ?>

                            <?= nl2br(
                                htmlspecialchars($result["admin_comment"])
                            ) ?>

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>


        <br>


        <?php if ($has_submitted): ?>
            

            <!-- Admin Decision -->

            <h3>Admin Decision</h3>

            <form method="POST">

                <input
                    type="hidden"
                    name="faculty_subject_id"
                    value="<?= $selected_subject_id ?>"
                >


                <label for="admin_comment">
                    Admin Comment
                </label>

                <br>

                <textarea
                    name="admin_comment"
                    id="admin_comment"
                    rows="4"
                    cols="60"
                    placeholder="Enter comment or rejection reason..."
                ></textarea>

                <br><br>


                <button
                    type="submit"
                    name="action"
                    value="approve"
                    onclick="return confirm(
                        'Approve these results?'
                    );"
                >
                    Approve Results
                </button>


                <button
                    type="submit"
                    name="action"
                    value="reject"
                    onclick="return confirm(
                        'Reject these results and return them to faculty?'
                    );"
                >
                    Reject Results
                </button>

            </form>


        <?php elseif ($has_approved): ?>

                <p>
                    ✅ These results have been approved.
                    They are ready for publishing.
                </p>

                <form method="POST">

                    <input
                        type="hidden"
                        name="faculty_subject_id"
                        value="<?= $selected_subject_id ?>"
                    >

                    <button
                        type="submit"
                        name="action"
                        value="publish"
                        onclick="return confirm(
                            'Publish these results? Students will be able to view them.'
                        );"
                    >
                        Publish Results
                    </button>

                </form>


        <?php elseif ($has_rejected): ?>

            <p style="color: red;">
                ❌ These results were rejected.
                Faculty must correct and resubmit them.
            </p>


        <?php else: ?>

            <p>
                No pending Admin action.
            </p>

        <?php endif; ?>


        <br>

        <a href="dashboard.php">
            ← Back to Admin Dashboard
        </a>

    <?php endif; ?>

</div>

</body>

</html>