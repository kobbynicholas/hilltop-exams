<?php

session_start();

require_once "../config/db.php";

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)($_GET["student_id"] ?? 0);
$class_id   = (int)($_GET["class_id"] ?? 0);
$term_id    = (int)($_GET["term_id"] ?? 0);

if (
    !$student_id ||
    !$class_id ||
    !$term_id
) {
    die("Invalid report information.");
}


/*
|--------------------------------------------------------------------------
| STUDENT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        c.class_name
    FROM students s

    INNER JOIN classes c
        ON c.id = s.class_id

    WHERE
        s.id = ?
        AND s.class_id = ?

    LIMIT 1
");

$stmt->execute([
    $student_id,
    $class_id
]);

$student = $stmt->fetch();

if (!$student) {
    die("Student not found.");
}


/*
|--------------------------------------------------------------------------
| TERM
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        t.term_name,
        ay.academic_year
    FROM terms t

    INNER JOIN academic_years ay
        ON ay.id = t.academic_year_id

    WHERE t.id = ?

    LIMIT 1
");

$stmt->execute([$term_id]);

$term = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| SAVE
|--------------------------------------------------------------------------
*/

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $days_opened =
        (int)($_POST["days_opened"] ?? 0);

    $days_present =
        (int)($_POST["days_present"] ?? 0);

    $days_absent =
        (int)($_POST["days_absent"] ?? 0);

    $conduct =
        trim($_POST["conduct"] ?? "");

    $teacher_comment =
        trim($_POST["teacher_comment"] ?? "");

    $headteacher_comment =
        trim($_POST["headteacher_comment"] ?? "");

    $promotion_status =
        trim($_POST["promotion_status"] ?? "");


    $stmt = $conn->prepare("
        INSERT INTO report_card_records
        (
            student_id,
            class_id,
            term_id,
            days_opened,
            days_present,
            days_absent,
            conduct,
            teacher_comment,
            headteacher_comment,
            promotion_status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE

            days_opened = VALUES(days_opened),
            days_present = VALUES(days_present),
            days_absent = VALUES(days_absent),
            conduct = VALUES(conduct),
            teacher_comment =
                VALUES(teacher_comment),
            headteacher_comment =
                VALUES(headteacher_comment),
            promotion_status =
                VALUES(promotion_status)
    ");

    $stmt->execute([
        $student_id,
        $class_id,
        $term_id,
        $days_opened,
        $days_present,
        $days_absent,
        $conduct,
        $teacher_comment,
        $headteacher_comment,
        $promotion_status
    ]);

    $message =
        "Report information saved successfully.";
}


/*
|--------------------------------------------------------------------------
| GET EXISTING INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM report_card_records
    WHERE
        student_id = ?
        AND class_id = ?
        AND term_id = ?
    LIMIT 1
");

$stmt->execute([
    $student_id,
    $class_id,
    $term_id
]);

$report = $stmt->fetch();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    HIBS Reports | Report Details
</title>

<link rel="stylesheet"
      href="../assets/css/style.css">

<style>

.comment-field {
    width: 100%;
    min-height: 120px;
    padding: 12px;
    border: 1px solid #d8d0c8;
    resize: vertical;
    font-family: Arial, sans-serif;
}

</style>

</head>

<body>

<header class="hibs-header">

    <div class="brand">

        <div class="brand-mark">H</div>

        <div class="brand-text">

            <h1>HIBS REPORTS</h1>

            <span>
                HILLTOP INTERNATIONAL BRITISH SCHOOL
            </span>

        </div>

    </div>

    <div class="top-user">

        <strong>
            <?= htmlspecialchars(
                $_SESSION["full_name"]
            ) ?>
        </strong>

        <a href="../logout.php"
           class="logout-link">
            Sign out
        </a>

    </div>

</header>


<nav class="hibs-nav">

    <a href="dashboard.php">Dashboard</a>
    <a href="students.php">Students</a>
    <a href="classes.php">Classes</a>
    <a href="subjects.php">Subjects</a>
    <a href="teachers.php">Teachers</a>
    <a href="results.php">Results</a>
    <a href="report_cards.php"
       class="active">
        Report Cards
    </a>

</nav>


<main class="page">

    <div class="page-heading">

        <div>

            <h2>
                Report Details
            </h2>

            <p>

                <?= htmlspecialchars(
                    $student["first_name"]
                ) ?>

                <?= htmlspecialchars(
                    $student["last_name"]
                ) ?>

                ·

                <?= htmlspecialchars(
                    $student["class_name"]
                ) ?>

                ·

                <?= htmlspecialchars(
                    $term["term_name"]
                ) ?>

            </p>

        </div>

    </div>


    <?php if ($message): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <div class="form-panel">

        <form method="POST">

            <h3 style="
                color:#641c2b;
                font-weight:normal;
                margin-bottom:20px;
            ">
                Attendance
            </h3>


            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Days School Opened
                    </label>

                    <input
                        type="number"
                        name="days_opened"
                        min="0"
                        value="<?= (int)(
                            $report["days_opened"] ?? 0
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Days Present
                    </label>

                    <input
                        type="number"
                        name="days_present"
                        min="0"
                        value="<?= (int)(
                            $report["days_present"] ?? 0
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Days Absent
                    </label>

                    <input
                        type="number"
                        name="days_absent"
                        min="0"
                        value="<?= (int)(
                            $report["days_absent"] ?? 0
                        ) ?>"
                        required
                    >

                </div>

            </div>


            <h3 style="
                color:#641c2b;
                font-weight:normal;
                margin:30px 0 20px;
            ">
                Conduct
            </h3>


            <div class="form-group">

                <select
                    name="conduct"
                    required
                >

                    <option value="">
                        Select Conduct
                    </option>

                    <?php

                    $conductOptions = [
                        "Excellent",
                        "Very Good",
                        "Good",
                        "Satisfactory",
                        "Needs Improvement"
                    ];

                    foreach ($conductOptions as $option):

                    ?>

                        <option
                            value="<?= htmlspecialchars(
                                $option
                            ) ?>"
                            <?= (
                                ($report["conduct"] ?? "")
                                === $option
                            )
                                ? "selected"
                                : "" ?>
                        >

                            <?= htmlspecialchars(
                                $option
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <h3 style="
                color:#641c2b;
                font-weight:normal;
                margin:30px 0 20px;
            ">
                Teacher's Comment
            </h3>


            <textarea
                name="teacher_comment"
                class="comment-field"
                placeholder="Enter teacher's comment..."
            ><?= htmlspecialchars(
                $report["teacher_comment"] ?? ""
            ) ?></textarea>


            <h3 style="
                color:#641c2b;
                font-weight:normal;
                margin:30px 0 20px;
            ">
                Headteacher's Comment
            </h3>


            <textarea
                name="headteacher_comment"
                class="comment-field"
                placeholder="Enter headteacher's comment..."
            ><?= htmlspecialchars(
                $report["headteacher_comment"] ?? ""
            ) ?></textarea>


            <h3 style="
                color:#641c2b;
                font-weight:normal;
                margin:30px 0 20px;
            ">
                Promotion Status
            </h3>


            <div class="form-group">

                <select
                    name="promotion_status"
                    required
                >

                    <option value="">
                        Select Status
                    </option>

                    <option
                        value="Promoted"
                        <?= (
                            ($report["promotion_status"] ?? "")
                            === "Promoted"
                        )
                            ? "selected"
                            : "" ?>
                    >
                        Promoted
                    </option>

                    <option
                        value="Conditional"
                        <?= (
                            ($report["promotion_status"] ?? "")
                            === "Conditional"
                        )
                            ? "selected"
                            : "" ?>
                    >
                        Conditional Promotion
                    </option>

                    <option
                        value="Not Promoted"
                        <?= (
                            ($report["promotion_status"] ?? "")
                            === "Not Promoted"
                        )
                            ? "selected"
                            : "" ?>
                    >
                        Not Promoted
                    </option>

                    <option
                        value="Pending"
                        <?= (
                            ($report["promotion_status"] ?? "")
                            === "Pending"
                        )
                            ? "selected"
                            : "" ?>
                    >
                        Pending
                    </option>

                </select>

            </div>


            <div style="
                margin-top:30px;
            ">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Report Details
                </button>


                <a
                    href="../student_report.php?student_id=<?= $student_id ?>&class_id=<?= $class_id ?>&term_id=<?= $term_id ?>"
                    target="_blank"
                    class="btn btn-gold"
                >
                    Preview Report
                </a>

            </div>

        </form>

    </div>

</main>

</body>

</html>
