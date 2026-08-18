<?php

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(
        random_bytes(32)
    );
}

$csrfToken = $_SESSION["csrf_token"];


/*
|--------------------------------------------------------------------------
| PARAMETERS
|--------------------------------------------------------------------------
*/

$student_id = filter_input(
    INPUT_GET,
    "student_id",
    FILTER_VALIDATE_INT
);

$class_id = filter_input(
    INPUT_GET,
    "class_id",
    FILTER_VALIDATE_INT
);

$term_id = filter_input(
    INPUT_GET,
    "term_id",
    FILTER_VALIDATE_INT
);


if (
    !$student_id ||
    !$class_id ||
    !$term_id
) {
    die("Invalid report information.");
}


/*
|--------------------------------------------------------------------------
| LOAD STUDENT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.gender,
        s.photo,

        c.id AS class_id,
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

$student = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$student) {
    die("Student not found.");
}


/*
|--------------------------------------------------------------------------
| LOAD TERM
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        t.id,
        t.term_name,
        t.academic_year_id,

        ay.academic_year

    FROM terms t

    INNER JOIN academic_years ay
        ON ay.id = t.academic_year_id

    WHERE t.id = ?

    LIMIT 1
");

$stmt->execute([
    $term_id
]);

$term = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$term) {
    die("Term not found.");
}


/*
|--------------------------------------------------------------------------
| LOAD SCHOOL SETTINGS
|--------------------------------------------------------------------------
*/

$school = null;

try {

    $stmt = $conn->query("
        SELECT *
        FROM school_settings
        ORDER BY id ASC
        LIMIT 1
    ");

    $school = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $school = null;
}


$schoolName =
    $school["school_name"]
    ?? "HILLTOP INTERNATIONAL BRITISH SCHOOL";

$headteacherName =
    $school["headteacher_name"]
    ?? "";

$principalTitle =
    $school["principal_title"]
    ?? "Headteacher";


/*
|--------------------------------------------------------------------------
| LOAD STUDENT OVERALL RESULT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        total_score,
        average_score,
        position,
        class_size

    FROM student_results

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

$overall = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| AUTOMATIC TEACHER COMMENT
|--------------------------------------------------------------------------
*/

$average =
    $overall
    ? (float)$overall["average_score"]
    : 0;


function generateTeacherComment(
    float $average
): string {

    if ($average >= 90) {

        return
            "An outstanding performance. "
            . "The student has demonstrated excellent "
            . "understanding and consistently high achievement. "
            . "This excellent standard should be maintained.";

    }

    if ($average >= 80) {

        return
            "Excellent performance. "
            . "The student has demonstrated a very strong "
            . "understanding of the work covered and has made "
            . "commendable progress throughout the term.";

    }

    if ($average >= 70) {

        return
            "Very good performance. "
            . "The student has demonstrated good understanding "
            . "of the subjects covered and is making very good "
            . "academic progress.";

    }

    if ($average >= 60) {

        return
            "A good performance. "
            . "The student has made satisfactory progress "
            . "during the term. Greater consistency and "
            . "continued effort will support further improvement.";

    }

    if ($average >= 50) {

        return
            "The student has made satisfactory progress. "
            . "More consistent effort, regular revision and "
            . "greater participation in lessons are encouraged.";

    }

    if ($average >= 40) {

        return
            "The student needs to improve academic performance. "
            . "Greater effort, regular revision and additional "
            . "academic support are recommended.";

    }

    return
        "The student's current academic performance requires "
        . "significant improvement. A structured programme of "
        . "additional support, regular revision and close "
        . "monitoring is recommended.";
}


/*
|--------------------------------------------------------------------------
| AUTOMATIC PROMOTION RECOMMENDATION
|--------------------------------------------------------------------------
*/

function generatePromotionRecommendation(
    float $average,
    float $attendance
): string {

    if (
        $average >= 50 &&
        $attendance >= 75
    ) {

        return "Promoted";
    }


    if (
        $average >= 45 &&
        $attendance >= 70
    ) {

        return "Conditional";
    }


    if (
        $average > 0
    ) {

        return "Not Promoted";
    }


    return "Pending";
}


/*
|--------------------------------------------------------------------------
| LOAD EXISTING REPORT DETAILS
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

$report = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$daysOpened =
    isset($report["days_opened"])
    ? (int)$report["days_opened"]
    : 0;

$daysPresent =
    isset($report["days_present"])
    ? (int)$report["days_present"]
    : 0;

$daysAbsent =
    isset($report["days_absent"])
    ? (int)$report["days_absent"]
    : 0;

$conduct =
    $report["conduct"]
    ?? "";

$teacherComment =
    $report["teacher_comment"]
    ?? "";

$headteacherComment =
    $report["headteacher_comment"]
    ?? "";

$promotionStatus =
    $report["promotion_status"]
    ?? "Pending";

$reportStatus =
    $report["report_status"]
    ?? "Draft";


/*
|--------------------------------------------------------------------------
| ATTENDANCE CALCULATION
|--------------------------------------------------------------------------
*/

$attendancePercentage = 0;

if ($daysOpened > 0) {

    $attendancePercentage =
        ($daysPresent / $daysOpened) * 100;
}


/*
|--------------------------------------------------------------------------
| AUTOMATIC SUGGESTIONS
|--------------------------------------------------------------------------
*/

$automaticComment =
    generateTeacherComment($average);

$automaticPromotion =
    generatePromotionRecommendation(
        $average,
        $attendancePercentage
    );


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

$message = "";
$error = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | CSRF CHECK
    |--------------------------------------------------------------------------
    */

    $postedToken =
        $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $_SESSION["csrf_token"],
            $postedToken
        )
    ) {

        $error =
            "Security verification failed. "
            . "Please refresh the page and try again.";

    } elseif ($reportStatus === "Published") {

        $error =
            "This report has already been published "
            . "and can no longer be edited.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | GET FORM VALUES
        |--------------------------------------------------------------------------
        */

        $daysOpened =
            filter_var(
                $_POST["days_opened"] ?? null,
                FILTER_VALIDATE_INT
            );

        $daysPresent =
            filter_var(
                $_POST["days_present"] ?? null,
                FILTER_VALIDATE_INT
            );

        $conduct =
            trim(
                $_POST["conduct"] ?? ""
            );

        $teacherComment =
            trim(
                $_POST["teacher_comment"] ?? ""
            );

        $headteacherComment =
            trim(
                $_POST["headteacher_comment"] ?? ""
            );

        $promotionStatus =
            trim(
                $_POST["promotion_status"] ?? ""
            );


        /*
        |--------------------------------------------------------------------------
        | VALIDATE ATTENDANCE
        |--------------------------------------------------------------------------
        */

        if (
            $daysOpened === false ||
            $daysOpened === null ||
            $daysOpened < 0
        ) {

            $error =
                "Days school opened must be a valid number.";

        } elseif (
            $daysPresent === false ||
            $daysPresent === null ||
            $daysPresent < 0
        ) {

            $error =
                "Days present must be a valid number.";

        } elseif (
            $daysPresent > $daysOpened
        ) {

            $error =
                "Days present cannot be greater than "
                . "days school opened.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | CALCULATE ABSENCE AUTOMATICALLY
            |--------------------------------------------------------------------------
            */

            $daysAbsent =
                $daysOpened - $daysPresent;


            /*
            |--------------------------------------------------------------------------
            | CALCULATE ATTENDANCE PERCENTAGE
            |--------------------------------------------------------------------------
            */

            if ($daysOpened > 0) {

                $attendancePercentage =
                    (
                        $daysPresent /
                        $daysOpened
                    ) * 100;

            } else {

                $attendancePercentage = 0;
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATE CONDUCT
            |--------------------------------------------------------------------------
            */

            $allowedConduct = [
                "Excellent",
                "Very Good",
                "Good",
                "Satisfactory",
                "Needs Improvement"
            ];


            if (
                !in_array(
                    $conduct,
                    $allowedConduct,
                    true
                )
            ) {

                $error =
                    "Please select a valid conduct rating.";

            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATE PROMOTION STATUS
            |--------------------------------------------------------------------------
            */

            $allowedPromotion = [
                "Promoted",
                "Conditional",
                "Not Promoted",
                "Pending"
            ];


            if (
                $error === "" &&
                !in_array(
                    $promotionStatus,
                    $allowedPromotion,
                    true
                )
            ) {

                $error =
                    "Please select a valid promotion status.";

            }


            /*
            |--------------------------------------------------------------------------
            | SAVE REPORT
            |--------------------------------------------------------------------------
            */

            if ($error === "") {

                try {

                    /*
                    |----------------------------------------------------------
                    | HEADTEACHER COMMENT DEFAULT
                    |----------------------------------------------------------
                    */

                    if ($headteacherComment === "") {

                        $headteacherComment =
                            "The student has completed "
                            . "the academic term. "
                            . "Continued effort and commitment "
                            . "to learning are encouraged.";
                    }


                    /*
                    |----------------------------------------------------------
                    | TEACHER NAME
                    |----------------------------------------------------------
                    */

                    $teacherName =
                        $report["teacher_name"]
                        ?? null;


                    /*
                    |----------------------------------------------------------
                    | SAVE
                    |----------------------------------------------------------
                    */

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

                            promotion_status,

                            report_status,

                            teacher_name,
                            headteacher_name

                        )

                        VALUES
                        (
                            ?,
                            ?,
                            ?,

                            ?,
                            ?,
                            ?,

                            ?,

                            ?,
                            ?,

                            ?,

                            'Draft',

                            ?,
                            ?
                        )

                        ON DUPLICATE KEY UPDATE

                            days_opened =
                                VALUES(days_opened),

                            days_present =
                                VALUES(days_present),

                            days_absent =
                                VALUES(days_absent),

                            conduct =
                                VALUES(conduct),

                            teacher_comment =
                                VALUES(teacher_comment),

                            headteacher_comment =
                                VALUES(headteacher_comment),

                            promotion_status =
                                VALUES(promotion_status),

                            teacher_name =
                                VALUES(teacher_name),

                            headteacher_name =
                                VALUES(headteacher_name)
                    ");


                    $stmt->execute([

                        $student_id,
                        $class_id,
                        $term_id,

                        $daysOpened,
                        $daysPresent,
                        $daysAbsent,

                        $conduct,

                        $teacherComment,
                        $headteacherComment,

                        $promotionStatus,

                        $teacherName,
                        $headteacherName

                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | SUCCESS
                    |--------------------------------------------------------------------------
                    */

                    $message =
                        "Report details saved successfully.";


                    /*
                    |--------------------------------------------------------------------------
                    | RELOAD RECORD
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

                    $report =
                        $stmt->fetch(PDO::FETCH_ASSOC);


                    $reportStatus =
                        $report["report_status"]
                        ?? "Draft";


                } catch (PDOException $e) {

                    $error =
                        "The report could not be saved. "
                        . "Database error: "
                        . $e->getMessage();
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| POSITION TEXT
|--------------------------------------------------------------------------
*/

$positionText = "Not calculated";


if ($overall) {

    $position =
        (int)$overall["position"];

    $classSize =
        (int)$overall["class_size"];


    if (
        $position > 0 &&
        $classSize > 0
    ) {

        $suffix = "th";


        if (
            $position % 100 < 11 ||
            $position % 100 > 13
        ) {

            switch (
                $position % 10
            ) {

                case 1:
                    $suffix = "st";
                    break;

                case 2:
                    $suffix = "nd";
                    break;

                case 3:
                    $suffix = "rd";
                    break;
            }
        }


        $positionText =
            $position .
            $suffix .
            " out of " .
            $classSize;
    }
}


/*
|--------------------------------------------------------------------------
| REPORT STATUS CLASS
|--------------------------------------------------------------------------
*/

$statusClass =
    strtolower(
        $reportStatus
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    HIBS Reports | Report Details
</title>

<link
    rel="stylesheet"
    href="../assets/css/style.css"
>


<style>

/* =========================================================
   PAGE
========================================================= */

.details-container {
    max-width: 1200px;
    margin: 0 auto;
}


/* =========================================================
   STUDENT HEADER
========================================================= */

.student-header {
    background: #ffffff;
    border: 1px solid #e2ddd6;
    padding: 25px;
    margin-bottom: 22px;

    display: flex;
    align-items: center;
    gap: 22px;
}

.student-photo {
    width: 90px;
    height: 105px;

    object-fit: cover;

    border: 2px solid #641c2b;

    background: #f5f1eb;
}

.student-photo-placeholder {
    width: 90px;
    height: 105px;

    border: 2px solid #641c2b;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #f5f1eb;

    color: #777;

    font-family: Arial, sans-serif;
    font-size: 10px;
}

.student-header h2 {
    margin: 0 0 6px;

    color: #641c2b;

    font-weight: normal;
}

.student-header p {
    margin: 4px 0;

    color: #777;

    font-family: Arial, sans-serif;
    font-size: 12px;
}


/* =========================================================
   STATUS
========================================================= */

.report-status {
    margin-left: auto;

    text-align: right;
}

.status-label {
    display: block;

    font-family: Arial, sans-serif;

    font-size: 9px;

    color: #888;

    text-transform: uppercase;

    letter-spacing: 1px;

    margin-bottom: 6px;
}

.status-badge {
    display: inline-block;

    padding: 7px 14px;

    font-family: Arial, sans-serif;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .7px;
}

.status-draft {
    background: #eee;
    color: #555;
}

.status-approved {
    background: #f0e6cf;
    color: #74571d;
}

.status-published {
    background: #e3eee7;
    color: #2f6142;
}


/* =========================================================
   PANELS
========================================================= */

.details-panel {
    background: #ffffff;

    border: 1px solid #e2ddd6;

    padding: 28px;

    margin-bottom: 22px;
}

.details-panel h3 {
    margin: 0 0 20px;

    color: #641c2b;

    font-size: 18px;

    font-weight: normal;

    padding-bottom: 10px;

    border-bottom: 1px solid #b58a3a;
}


/* =========================================================
   PERFORMANCE SUMMARY
========================================================= */

.performance-summary {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}

.performance-card {
    background: #f8f4ee;

    border: 1px solid #e3dbd1;

    padding: 18px;

    text-align: center;
}

.performance-card span {
    display: block;

    font-family: Arial, sans-serif;

    font-size: 9px;

    color: #777;

    text-transform: uppercase;

    letter-spacing: .7px;

    margin-bottom: 7px;
}

.performance-card strong {
    display: block;

    color: #641c2b;

    font-size: 22px;
}


/* =========================================================
   ATTENDANCE
========================================================= */

.attendance-preview {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;

    margin-top: 15px;
}

.attendance-card {
    border: 1px solid #e1d9d0;

    padding: 15px;

    text-align: center;
}

.attendance-card span {
    display: block;

    font-family: Arial, sans-serif;

    font-size: 9px;

    color: #777;

    text-transform: uppercase;

    margin-bottom: 5px;
}

.attendance-card strong {
    color: #641c2b;

    font-size: 20px;
}


/* =========================================================
   COMMENT GENERATOR
========================================================= */

.comment-tools {
    display: flex;

    gap: 8px;

    flex-wrap: wrap;

    margin-top: 10px;
}

.comment-tools button {
    border: none;

    cursor: pointer;
}


/* =========================================================
   HELP TEXT
========================================================= */

.help-text {
    display: block;

    margin-top: 6px;

    color: #888;

    font-family: Arial, sans-serif;

    font-size: 10px;
}


/* =========================================================
   READ-ONLY
========================================================= */

.locked-notice {
    background: #f3eee7;

    border-left: 4px solid #b58a3a;

    padding: 14px 16px;

    margin-bottom: 20px;

    color: #5c514b;

    font-family: Arial, sans-serif;

    font-size: 12px;
}


/* =========================================================
   ACTION BAR
========================================================= */

.action-bar {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    flex-wrap: wrap;

    margin-top: 25px;
}

.action-left,
.action-right {
    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 800px) {

    .student-header {
        flex-direction: column;

        text-align: center;
    }

    .report-status {
        margin-left: 0;

        text-align: center;
    }

    .performance-summary {
        grid-template-columns: 1fr;
    }

    .attendance-preview {
        grid-template-columns: 1fr 1fr;
    }

}

@media (max-width: 500px) {

    .attendance-preview {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<header class="hibs-header">

    <div class="brand">

        <div class="brand-mark">
            H
        </div>

        <div class="brand-text">

            <h1>
                HIBS REPORTS
            </h1>

            <span>
                HILLTOP INTERNATIONAL BRITISH SCHOOL
            </span>

        </div>

    </div>


    <div class="top-user">

        <strong>
            <?= htmlspecialchars(
                $_SESSION["full_name"] ?? "Administrator"
            ) ?>
        </strong>

        <a
            href="../logout.php"
            class="logout-link"
        >
            Sign out
        </a>

    </div>

</header>


<!-- =====================================================
     NAVIGATION
===================================================== -->

<nav class="hibs-nav">

    <a href="dashboard.php">
        Dashboard
    </a>

    <a href="students.php">
        Students
    </a>

    <a href="classes.php">
        Classes
    </a>

    <a href="subjects.php">
        Subjects
    </a>

    <a href="teachers.php">
        Teachers
    </a>

    <a href="academic_years.php">
        Academic Years
    </a>

    <a href="terms.php">
        Terms
    </a>

    <a href="class_subjects.php">
        Class Subjects
    </a>

    <a href="assessments.php">
        Assessments
    </a>

    <a href="subject_assessments.php">
        Assessment Setup
    </a>

    <a href="grades.php">
        Grades
    </a>

    <a href="marks.php">
        Marks
    </a>

    <a href="results.php">
        Results
    </a>

    <a
        href="report_cards.php"
        class="active"
    >
        Report Cards
    </a>

    <a href="attendance.php">
        Attendance
    </a>

    <a href="reports.php">
        Reports
    </a>

    <a href="settings.php">
        Settings
    </a>

</nav>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="page">

<div class="details-container">


    <!-- =================================================
         PAGE HEADING
    ================================================== -->

    <div class="page-heading">

        <div>

            <h2>
                Report Details
            </h2>

            <p>

                <?= htmlspecialchars(
                    $term["academic_year"]
                ) ?>

                &nbsp;·&nbsp;

                <?= htmlspecialchars(
                    $term["term_name"]
                ) ?>

            </p>

        </div>

    </div>


    <!-- =================================================
         MESSAGES
    ================================================== -->

    <?php if ($message): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars(
                $message
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         STUDENT HEADER
    ================================================== -->

    <div class="student-header">


        <?php if (
            !empty($student["photo"])
        ): ?>

            <img
                src="../uploads/students/<?= htmlspecialchars(
                    $student["photo"]
                ) ?>"
                class="student-photo"
                alt="Student photograph"
            >

        <?php else: ?>

            <div class="student-photo-placeholder">
                NO PHOTO
            </div>

        <?php endif; ?>


        <div>

            <h2>

                <?= htmlspecialchars(
                    $student["first_name"]
                ) ?>

                <?php if (
                    !empty($student["middle_name"])
                ): ?>

                    <?= htmlspecialchars(
                        " " .
                        $student["middle_name"]
                    ) ?>

                <?php endif; ?>

                <?= htmlspecialchars(
                    " " .
                    $student["last_name"]
                ) ?>

            </h2>


            <p>

                <strong>
                    Student ID:
                </strong>

                <?= htmlspecialchars(
                    $student["student_id"]
                ) ?>

            </p>


            <p>

                <strong>
                    Class:
                </strong>

                <?= htmlspecialchars(
                    $student["class_name"]
                ) ?>

            </p>


            <p>

                <strong>
                    Academic Year:
                </strong>

                <?= htmlspecialchars(
                    $term["academic_year"]
                ) ?>

                &nbsp;|&nbsp;

                <strong>
                    Term:
                </strong>

                <?= htmlspecialchars(
                    $term["term_name"]
                ) ?>

            </p>

        </div>


        <div class="report-status">

            <span class="status-label">
                Report Status
            </span>


            <span
                class="status-badge status-<?= htmlspecialchars(
                    $statusClass
                ) ?>"
            >

                <?= htmlspecialchars(
                    $reportStatus
                ) ?>

            </span>

        </div>

    </div>


    <!-- =================================================
         PERFORMANCE
    ================================================== -->

    <div class="details-panel">

        <h3>
            Academic Performance Summary
        </h3>


        <div class="performance-summary">


            <div class="performance-card">

                <span>
                    Overall Average
                </span>

                <strong>

                    <?= $overall
                        ? number_format(
                            (float)$overall["average_score"],
                            2
                        ) . "%"
                        : "N/A"
                    ?>

                </strong>

            </div>


            <div class="performance-card">

                <span>
                    Position
                </span>

                <strong>

                    <?= htmlspecialchars(
                        $positionText
                    ) ?>

                </strong>

            </div>


            <div class="performance-card">

                <span>
                    Total Score
                </span>

                <strong>

                    <?= $overall
                        ? number_format(
                            (float)$overall["total_score"],
                            2
                        )
                        : "N/A"
                    ?>

                </strong>

            </div>

        </div>


        <?php if (!$overall): ?>

            <div class="alert alert-danger">

                No calculated academic result exists
                for this student and term.

                Please calculate the student's results
                before finalising this report.

            </div>

        <?php endif; ?>

    </div>


    <!-- =================================================
         LOCKED NOTICE
    ================================================== -->

    <?php if (
        $reportStatus === "Published"
    ): ?>

        <div class="locked-notice">

            <strong>
                This report is published.
            </strong>

            Published reports are locked and cannot
            be edited from this page.

        </div>

    <?php endif; ?>


    <!-- =================================================
         FORM
    ================================================== -->

    <form
        method="POST"
        autocomplete="off"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken
            ) ?>"
        >


        <!-- =============================================
             ATTENDANCE
        ============================================== -->

        <div class="details-panel">

            <h3>
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
                        max="366"
                        value="<?= $daysOpened ?>"
                        <?= $reportStatus === "Published"
                            ? "disabled"
                            : "" ?>
                        required
                    >

                    <span class="help-text">
                        Enter the total number of school days
                        for this term.
                    </span>

                </div>


                <div class="form-group">

                    <label>
                        Days Present
                    </label>

                    <input
                        type="number"
                        name="days_present"
                        min="0"
                        max="366"
                        value="<?= $daysPresent ?>"
                        <?= $reportStatus === "Published"
                            ? "disabled"
                            : "" ?>
                        required
                    >

                    <span class="help-text">
                        Days the student was present.
                    </span>

                </div>


                <div class="form-group">

                    <label>
                        Days Absent
                    </label>

                    <input
                        type="number"
                        value="<?= $daysAbsent ?>"
                        disabled
                    >

                    <span class="help-text">
                        Automatically calculated as
                        opened minus present.
                    </span>

                </div>

            </div>


            <div class="attendance-preview">

                <div class="attendance-card">

                    <span>
                        Opened
                    </span>

                    <strong>
                        <?= $daysOpened ?>
                    </strong>

                </div>


                <div class="attendance-card">

                    <span>
                        Present
                    </span>

                    <strong>
                        <?= $daysPresent ?>
                    </strong>

                </div>


                <div class="attendance-card">

                    <span>
                        Absent
                    </span>

                    <strong>
                        <?= $daysAbsent ?>
                    </strong>

                </div>


                <div class="attendance-card">

                    <span>
                        Attendance
                    </span>

                    <strong>

                        <?= number_format(
                            $attendancePercentage,
                            1
                        ) ?>%

                    </strong>

                </div>

            </div>

        </div>


        <!-- =============================================
             CONDUCT
        ============================================== -->

        <div class="details-panel">

            <h3>
                Conduct & Behaviour
            </h3>


            <div class="form-group">

                <label>
                    Conduct Rating
                </label>

                <select
                    name="conduct"
                    <?= $reportStatus === "Published"
                        ? "disabled"
                        : "" ?>
                    required
                >

                    <option value="">
                        Select Conduct Rating
                    </option>

                    <?php

                    $conductOptions = [
                        "Excellent",
                        "Very Good",
                        "Good",
                        "Satisfactory",
                        "Needs Improvement"
                    ];

                    foreach (
                        $conductOptions
                        as $option
                    ):

                    ?>

                        <option
                            value="<?= htmlspecialchars(
                                $option
                            ) ?>"
                            <?= $conduct === $option
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

        </div>


        <!-- =============================================
             TEACHER COMMENT
        ============================================== -->

        <div class="details-panel">

            <h3>
                Teacher's Comment
            </h3>


            <div class="form-group">

                <label>
                    Comment
                </label>

                <textarea
                    id="teacher_comment"
                    name="teacher_comment"
                    rows="6"
                    <?= $reportStatus === "Published"
                        ? "disabled"
                        : "" ?>
                    placeholder="Enter the class teacher's comment..."
                ><?= htmlspecialchars(
                    $teacherComment
                ) ?></textarea>

            </div>


            <?php if (
                $reportStatus !== "Published"
            ): ?>

                <div class="comment-tools">

                    <button
                        type="button"
                        class="btn btn-gold"
                        onclick="useAutomaticComment()"
                    >
                        Use Automatic Comment
                    </button>

                    <button
                        type="button"
                        class="btn btn-light"
                        onclick="clearTeacherComment()"
                    >
                        Clear
                    </button>

                </div>

            <?php endif; ?>


            <span class="help-text">

                Suggested comment based on the current
                academic average:

                <?= number_format(
                    $average,
                    2
                ) ?>%

            </span>

        </div>


        <!-- =============================================
             HEADTEACHER COMMENT
        ============================================== -->

        <div class="details-panel">

            <h3>
                Headteacher's Comment
            </h3>


            <div class="form-group">

                <label>
                    <?= htmlspecialchars(
                        $principalTitle
                    ) ?>'s Comment
                </label>

                <textarea
                    name="headteacher_comment"
                    rows="6"
                    <?= $reportStatus === "Published"
                        ? "disabled"
                        : "" ?>
                    placeholder="Enter the headteacher's comment..."
                ><?= htmlspecialchars(
                    $headteacherComment
                ) ?></textarea>

            </div>

        </div>


        <!-- =============================================
             PROMOTION
        ============================================== -->

        <div class="details-panel">

            <h3>
                Promotion Decision
            </h3>


            <div class="form-group">

                <label>
                    Promotion Status
                </label>

                <select
                    name="promotion_status"
                    <?= $reportStatus === "Published"
                        ? "disabled"
                        : "" ?>
                    required
                >

                    <option value="">
                        Select Promotion Status
                    </option>

                    <option
                        value="Promoted"
                        <?= $promotionStatus === "Promoted"
                            ? "selected"
                            : "" ?>
                    >
                        Promoted
                    </option>

                    <option
                        value="Conditional"
                        <?= $promotionStatus === "Conditional"
                            ? "selected"
                            : "" ?>
                    >
                        Conditional Promotion
                    </option>

                    <option
                        value="Not Promoted"
                        <?= $promotionStatus === "Not Promoted"
                            ? "selected"
                            : "" ?>
                    >
                        Not Promoted
                    </option>

                    <option
                        value="Pending"
                        <?= $promotionStatus === "Pending"
                            ? "selected"
                            : "" ?>
                    >
                        Pending
                    </option>

                </select>

            </div>


            <div class="locked-notice">

                <strong>
                    System recommendation:
                </strong>

                <?= htmlspecialchars(
                    $automaticPromotion
                ) ?>

                <br><br>

                <small>

                    This is only a recommendation.
                    The administrator has the final authority
                    to select the official promotion status.

                </small>

            </div>

        </div>


        <!-- =============================================
             ACTIONS
        ============================================== -->

        <div class="action-bar">


            <div class="action-left">

                <a
                    href="report_cards.php?class_id=<?= $class_id ?>&term_id=<?= $term_id ?>"
                    class="btn btn-light"
                >
                    ← Back to Reports
                </a>


                <?php if (
                    $reportStatus !== "Published"
                ): ?>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save Report Details
                    </button>

                <?php endif; ?>

            </div>


            <div class="action-right">


                <a
                    href="../student_report.php?student_id=<?= $student_id ?>&class_id=<?= $class_id ?>&term_id=<?= $term_id ?>"
                    class="btn btn-gold"
                    target="_blank"
                >
                    Preview Report
                </a>


                <a
                    href="../student_report.php?student_id=<?= $student_id ?>&class_id=<?= $class_id ?>&term_id=<?= $term_id ?>&print=1"
                    class="btn btn-primary"
                    target="_blank"
                >
                    Print / PDF
                </a>

            </div>

        </div>


    </form>


</div>

</main>


<script>

/*
|--------------------------------------------------------------------------
| AUTOMATIC TEACHER COMMENT
|--------------------------------------------------------------------------
*/

const automaticComment =
<?= json_encode(
    $automaticComment,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
) ?>;


function useAutomaticComment() {

    const textarea =
        document.getElementById(
            "teacher_comment"
        );

    if (!textarea) {
        return;
    }


    if (
        textarea.value.trim() !== ""
    ) {

        const confirmed =
            confirm(
                "Replace the current teacher comment "
                + "with the automatic comment?"
            );

        if (!confirmed) {
            return;
        }
    }


    textarea.value =
        automaticComment;
}


function clearTeacherComment() {

    const textarea =
        document.getElementById(
            "teacher_comment"
        );

    if (!textarea) {
        return;
    }


    textarea.value = "";
}


/*
|--------------------------------------------------------------------------
| ATTENDANCE LIVE CALCULATION
|--------------------------------------------------------------------------
*/

const openedInput =
    document.querySelector(
        'input[name="days_opened"]'
    );

const presentInput =
    document.querySelector(
        'input[name="days_present"]'
    );


function updateAttendancePreview() {

    if (
        !openedInput ||
        !presentInput
    ) {
        return;
    }


    const opened =
        parseInt(
            openedInput.value,
            10
        ) || 0;


    const present =
        parseInt(
            presentInput.value,
            10
        ) || 0;


    const absent =
        Math.max(
            opened - present,
            0
        );


    let percentage = 0;


    if (opened > 0) {

        percentage =
            (present / opened) * 100;
    }


    const cards =
        document.querySelectorAll(
            ".attendance-card strong"
        );


    if (cards.length >= 4) {

        cards[0].textContent =
            opened;

        cards[1].textContent =
            present;

        cards[2].textContent =
            absent;

        cards[3].textContent =
            percentage.toFixed(1) + "%";
    }
}


if (openedInput) {

    openedInput.addEventListener(
        "input",
        updateAttendancePreview
    );
}


if (presentInput) {

    presentInput.addEventListener(
        "input",
        updateAttendancePreview
    );
}


</script>


</body>

</html>
