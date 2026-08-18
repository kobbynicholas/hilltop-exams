<?php

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORT CARDS
| ADMIN ACCESS ONLY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
|
| Keep database errors out of the browser in production.
|
*/

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    $_SESSION["csrf_token"];


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$message = "";
$messageType = "";


/*
|--------------------------------------------------------------------------
| HANDLE FLASH MESSAGE FROM OTHER PAGES
|--------------------------------------------------------------------------
*/

if (
    isset($_GET["approved"]) &&
    $_GET["approved"] === "1"
) {

    $message =
        "Report approved successfully.";

    $messageType =
        "success";
}


if (
    isset($_GET["published"]) &&
    $_GET["published"] === "1"
) {

    $message =
        "Report published successfully.";

    $messageType =
        "success";
}


/*
|--------------------------------------------------------------------------
| SELECTED FILTERS
|--------------------------------------------------------------------------
*/

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


$class_id =
    $class_id ?: 0;

$term_id =
    $term_id ?: 0;


/*
|--------------------------------------------------------------------------
| LOAD CLASSES
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT
            id,
            class_name

        FROM classes

        ORDER BY
            class_name ASC
    ");

    $classes =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $classes = [];

    $message =
        "Unable to load classes.";

    $messageType =
        "error";
}


/*
|--------------------------------------------------------------------------
| LOAD TERMS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT
            t.id,
            t.term_name,
            t.academic_year_id,

            ay.academic_year

        FROM terms t

        INNER JOIN academic_years ay
            ON ay.id = t.academic_year_id

        ORDER BY
            ay.id DESC,
            t.id DESC
    ");

    $terms =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $terms = [];

    if ($message === "") {

        $message =
            "Unable to load academic terms.";

        $messageType =
            "error";
    }
}


/*
|--------------------------------------------------------------------------
| SELECTED CLASS INFORMATION
|--------------------------------------------------------------------------
*/

$selectedClass = null;

if ($class_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            id,
            class_name

        FROM classes

        WHERE id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $class_id
    ]);

    $selectedClass =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );
}


/*
|--------------------------------------------------------------------------
| SELECTED TERM INFORMATION
|--------------------------------------------------------------------------
*/

$selectedTerm = null;

if ($term_id > 0) {

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

    $selectedTerm =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );
}


/*
|--------------------------------------------------------------------------
| STUDENT / REPORT DATA
|--------------------------------------------------------------------------
*/

$students = [];

$totalStudents = 0;
$draftReports = 0;
$approvedReports = 0;
$publishedReports = 0;
$missingReports = 0;
$completedReports = 0;


if (
    $class_id > 0 &&
    $term_id > 0
) {

    /*
    |--------------------------------------------------------------------------
    | LOAD STUDENTS
    |--------------------------------------------------------------------------
    */

    try {

        $stmt = $conn->prepare("
            SELECT

                s.id,
                s.student_id,
                s.first_name,
                s.middle_name,
                s.last_name,
                s.gender,
                s.photo,

                c.class_name,

                r.id AS report_id,
                r.report_status,
                r.days_opened,
                r.days_present,
                r.days_absent,
                r.conduct,
                r.promotion_status,

                r.teacher_comment,
                r.headteacher_comment,

                r.approved_at,
                r.published_at,

                sr.total_score,
                sr.average_score,
                sr.position,
                sr.class_size

            FROM students s

            INNER JOIN classes c
                ON c.id = s.class_id

            LEFT JOIN report_card_records r
                ON r.student_id = s.id
                AND r.class_id = s.class_id
                AND r.term_id = ?

            LEFT JOIN student_results sr
                ON sr.student_id = s.id
                AND sr.class_id = s.class_id
                AND sr.term_id = ?

            WHERE
                s.class_id = ?

                AND (
                    s.status = 'Active'
                    OR s.status IS NULL
                )

            ORDER BY
                s.last_name ASC,
                s.first_name ASC
        ");

        $stmt->execute([
            $term_id,
            $term_id,
            $class_id
        ]);

        $students =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (PDOException $e) {

        $students = [];

        $message =
            "Unable to load students and report information.";

        $messageType =
            "error";
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE SUMMARY COUNTS
    |--------------------------------------------------------------------------
    */

    foreach (
        $students
        as &$student
    ) {

        $totalStudents++;


        $status =
            $student["report_status"]
            ?? "Draft";


        if (
            !$student["report_id"]
        ) {

            $missingReports++;

        } elseif (
            $status === "Draft"
        ) {

            $draftReports++;

        } elseif (
            $status === "Approved"
        ) {

            $approvedReports++;

        } elseif (
            $status === "Published"
        ) {

            $publishedReports++;
        }


        /*
        |--------------------------------------------------------------------------
        | ACADEMIC RESULT CHECK
        |--------------------------------------------------------------------------
        */

        $student["has_result"] =
            $student["average_score"] !== null;


        /*
        |--------------------------------------------------------------------------
        | REPORT DETAILS CHECK
        |--------------------------------------------------------------------------
        */

        $student["has_details"] =
            !empty(
                $student["conduct"]
            )
            &&
            $student["days_opened"] !== null
            &&
            $student["promotion_status"] !== null;


        if (
            $student["has_result"] &&
            $student["has_details"] &&
            $student["report_id"]
        ) {

            $completedReports++;
        }


        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE %
        |--------------------------------------------------------------------------
        */

        $opened =
            (int)(
                $student["days_opened"]
                ?? 0
            );

        $present =
            (int)(
                $student["days_present"]
                ?? 0
            );


        if ($opened > 0) {

            $student["attendance_percentage"] =
                (
                    $present /
                    $opened
                ) * 100;

        } else {

            $student["attendance_percentage"] =
                0;
        }


        /*
        |--------------------------------------------------------------------------
        | DISPLAY NAME
        |--------------------------------------------------------------------------
        */

        $nameParts = [];

        if (
            !empty(
                $student["first_name"]
            )
        ) {

            $nameParts[] =
                $student["first_name"];
        }

        if (
            !empty(
                $student["middle_name"]
            )
        ) {

            $nameParts[] =
                $student["middle_name"];
        }

        if (
            !empty(
                $student["last_name"]
            )
        ) {

            $nameParts[] =
                $student["last_name"];
        }


        $student["full_name"] =
            trim(
                implode(
                    " ",
                    $nameParts
                )
            );
    }

    unset($student);
}


/*
|--------------------------------------------------------------------------
| COMPLETION PERCENTAGE
|--------------------------------------------------------------------------
*/

$completionPercentage = 0;

if ($totalStudents > 0) {

    $completionPercentage =
        (
            $completedReports /
            $totalStudents
        ) * 100;
}


/*
|--------------------------------------------------------------------------
| STATUS LABEL
|--------------------------------------------------------------------------
*/

function reportStatusBadge(
    ?string $status,
    bool $hasReport
): string {

    if (!$hasReport) {

        return '
            <span class="status-badge status-missing">
                Not Started
            </span>
        ';
    }


    switch ($status) {

        case "Approved":

            return '
                <span class="status-badge status-approved">
                    Approved
                </span>
            ';


        case "Published":

            return '
                <span class="status-badge status-published">
                    Published
                </span>
            ';


        default:

            return '
                <span class="status-badge status-draft">
                    Draft
                </span>
            ';
    }
}


/*
|--------------------------------------------------------------------------
| RESULT STATUS
|--------------------------------------------------------------------------
*/

function resultStatus(
    array $student
): string {

    if (
        !$student["has_result"]
    ) {

        return '
            <span class="mini-status danger">
                Results Missing
            </span>
        ';
    }


    return '
        <span class="mini-status success">
            Results Ready
        </span>
    ';
}


/*
|--------------------------------------------------------------------------
| REPORT DETAILS STATUS
|--------------------------------------------------------------------------
*/

function detailsStatus(
    array $student
): string {

    if (
        !$student["report_id"]
    ) {

        return '
            <span class="mini-status danger">
                Details Missing
            </span>
        ';
    }


    if (
        !$student["has_details"]
    ) {

        return '
            <span class="mini-status warning">
                Incomplete
            </span>
        ';
    }


    return '
        <span class="mini-status success">
            Complete
        </span>
    ';
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="theme-color"
    content="#641c2b"
>

<title>
    HIBS Reports | Report Cards
</title>


<link
    rel="stylesheet"
    href="../assets/css/style.css"
>


<style>

/* =========================================================
   HIBS REPORT CARDS
========================================================= */

* {
    box-sizing: border-box;
}


/* =========================================================
   PAGE
========================================================= */

.report-page {
    max-width: 1450px;
    margin: 0 auto;
}


/* =========================================================
   FILTER
========================================================= */

.filter-panel {
    background: #fffdf9;

    border: 1px solid #dfd6cc;

    padding: 24px;

    margin-bottom: 22px;

    box-shadow:
        0 5px 18px
        rgba(40, 25, 20, .04);
}

.filter-panel-header {
    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 15px;

    margin-bottom: 20px;
}

.filter-panel-header h3 {
    margin: 0;

    color: #641c2b;

    font-size: 18px;

    font-weight: normal;
}

.filter-panel-header p {
    margin: 5px 0 0;

    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 11px;
}


/* =========================================================
   FILTER FORM
========================================================= */

.filter-form {
    display: grid;

    grid-template-columns:
        1fr
        1fr
        auto;

    gap: 15px;

    align-items: end;
}

.filter-field label {
    display: block;

    margin-bottom: 7px;

    color: #6d625d;

    font-family: Arial, sans-serif;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .7px;
}

.filter-field select {
    width: 100%;

    min-height: 44px;

    padding: 10px 12px;

    border: 1px solid #d8cfc5;

    background: #fff;

    color: #30282a;

    font-family: Arial, sans-serif;

    font-size: 12px;

    outline: none;
}

.filter-field select:focus {
    border-color: #641c2b;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary-grid {
    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 14px;

    margin-bottom: 22px;
}

.summary-card {
    background: #fff;

    border: 1px solid #dfd6cc;

    padding: 18px;

    position: relative;

    overflow: hidden;
}

.summary-card::after {
    content: "";

    position: absolute;

    width: 65px;
    height: 65px;

    right: -22px;
    top: -22px;

    border-radius: 50%;

    background: #f5eee5;
}

.summary-card-label {
    display: block;

    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: .8px;

    margin-bottom: 8px;
}

.summary-card-number {
    color: #641c2b;

    font-size: 26px;

    font-weight: bold;

    position: relative;

    z-index: 1;
}

.summary-card-sub {
    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 10px;

    margin-top: 5px;
}


/* =========================================================
   COMPLETION
========================================================= */

.completion-panel {
    background: #fff;

    border: 1px solid #dfd6cc;

    padding: 18px;

    margin-bottom: 22px;
}

.completion-top {
    display: flex;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 9px;
}

.completion-top strong {
    color: #641c2b;

    font-family: Arial, sans-serif;

    font-size: 12px;
}

.completion-top span {
    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 11px;
}

.progress {
    height: 9px;

    background: #eee8e1;

    overflow: hidden;
}

.progress-bar {
    height: 100%;

    background: #641c2b;

    transition: width .3s ease;
}


/* =========================================================
   REPORT AREA
========================================================= */

.reports-panel {
    background: #fff;

    border: 1px solid #dfd6cc;

    overflow: hidden;
}

.reports-header {
    padding: 20px 22px;

    border-bottom: 1px solid #e7e0d9;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.reports-header h3 {
    margin: 0;

    color: #641c2b;

    font-size: 18px;

    font-weight: normal;
}

.reports-header p {
    margin: 5px 0 0;

    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 11px;
}


/* =========================================================
   TABLE
========================================================= */

.table-scroll {
    width: 100%;

    overflow-x: auto;
}

.reports-table {
    width: 100%;

    min-width: 1250px;

    border-collapse: collapse;
}

.reports-table th {
    padding: 12px 10px;

    background: #641c2b;

    color: #fff;

    border-right: 1px solid
        rgba(255,255,255,.15);

    font-family: Arial, sans-serif;

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .6px;

    text-align: left;

    white-space: nowrap;
}

.reports-table td {
    padding: 12px 10px;

    border-bottom: 1px solid #eee8e1;

    color: #514a47;

    font-family: Arial, sans-serif;

    font-size: 11px;

    vertical-align: middle;
}

.reports-table tbody tr:hover td {
    background: #fcfaf7;
}

.student-cell {
    display: flex;

    align-items: center;

    gap: 10px;

    min-width: 220px;
}

.student-photo {
    width: 42px;
    height: 48px;

    object-fit: cover;

    border: 1px solid #641c2b;

    background: #f4eee7;
}

.student-photo-placeholder {
    width: 42px;
    height: 48px;

    border: 1px solid #641c2b;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #f4eee7;

    color: #8b817b;

    font-size: 8px;
}

.student-name {
    color: #3c292d;

    font-weight: 700;

    line-height: 1.4;
}

.student-id {
    display: block;

    margin-top: 2px;

    color: #8b817b;

    font-size: 9px;

    font-weight: normal;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.status-badge {
    display: inline-block;

    padding: 6px 9px;

    font-family: Arial, sans-serif;

    font-size: 8px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .5px;

    white-space: nowrap;
}

.status-missing {
    background: #f5e8e6;

    color: #8c3028;
}

.status-draft {
    background: #eeeae5;

    color: #645b56;
}

.status-approved {
    background: #f4ecd9;

    color: #755a1e;
}

.status-published {
    background: #e5eee8;

    color: #315f42;
}


/* =========================================================
   MINI STATUS
========================================================= */

.mini-status {
    display: inline-block;

    margin-top: 4px;

    font-size: 8px;

    font-weight: 700;

    text-transform: uppercase;
}

.mini-status.success {
    color: #35734a;
}

.mini-status.warning {
    color: #9a7629;
}

.mini-status.danger {
    color: #a23a31;
}


/* =========================================================
   SCORE
========================================================= */

.score {
    color: #641c2b;

    font-size: 14px;

    font-weight: 700;
}

.position {
    color: #3f3033;

    font-weight: 700;
}


/* =========================================================
   ACTIONS
========================================================= */

.student-actions {
    display: flex;

    flex-wrap: wrap;

    gap: 5px;

    min-width: 250px;
}

.student-actions .btn {
    padding: 7px 9px;

    font-size: 9px;

    white-space: nowrap;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    padding: 65px 25px;

    text-align: center;
}

.empty-state-icon {
    font-size: 42px;

    margin-bottom: 12px;
}

.empty-state h3 {
    margin: 0;

    color: #641c2b;

    font-weight: normal;
}

.empty-state p {
    max-width: 480px;

    margin: 8px auto 0;

    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 11px;

    line-height: 1.6;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-actions {
    display: flex;

    flex-wrap: wrap;

    gap: 8px;
}

.quick-actions .btn {
    padding: 9px 12px;
}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width: 1050px) {

    .summary-grid {
        grid-template-columns:
            repeat(3, 1fr);
    }

}

@media(max-width: 800px) {

    .filter-form {
        grid-template-columns: 1fr;
    }

    .summary-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .filter-panel-header,
    .reports-header {
        flex-direction: column;

        align-items: flex-start;
    }

}

@media(max-width: 520px) {

    .summary-grid {
        grid-template-columns: 1fr;
    }

}


/* =========================================================
   PRINT
========================================================= */

@media print {

    .hibs-header,
    .hibs-nav,
    .filter-panel,
    .summary-grid,
    .completion-panel,
    .reports-header .quick-actions {
        display: none !important;
    }

    .page {
        margin: 0 !important;
        padding: 0 !important;
    }

    .reports-panel {
        border: none;
    }

    .reports-table {
        min-width: 0;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     HIBS HEADER
====================================================== -->

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
            <?= h(
                $_SESSION["full_name"]
                ?? "Administrator"
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
====================================================== -->

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
====================================================== -->

<main class="page">

<div class="report-page">


    <!-- =================================================
         PAGE HEADING
    ================================================== -->

    <div class="page-heading">

        <div>

            <h2>
                Student Report Cards
            </h2>

            <p>
                Manage, review, approve and publish
                official HIBS academic reports.
            </p>

        </div>

    </div>


    <!-- =================================================
         FLASH MESSAGE
    ================================================== -->

    <?php if ($message !== ""): ?>

        <div
            class="alert
            <?= $messageType === "error"
                ? "alert-danger"
                : "alert-success" ?>"
        >

            <?= h($message) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         FILTER PANEL
    ================================================== -->

    <section class="filter-panel">

        <div class="filter-panel-header">

            <div>

                <h3>
                    Select Reporting Period
                </h3>

                <p>
                    Select a class and academic term
                    to manage student reports.
                </p>

            </div>

        </div>


        <form
            method="GET"
            class="filter-form"
        >


            <!-- CLASS -->

            <div class="filter-field">

                <label>
                    Class
                </label>

                <select
                    name="class_id"
                    required
                >

                    <option value="">
                        Select Class
                    </option>

                    <?php foreach (
                        $classes
                        as $class
                    ): ?>

                        <option
                            value="<?= (int)$class["id"] ?>"
                            <?= $class_id ===
                                (int)$class["id"]
                                ? "selected"
                                : "" ?>
                        >

                            <?= h(
                                $class["class_name"]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- TERM -->

            <div class="filter-field">

                <label>
                    Academic Term
                </label>

                <select
                    name="term_id"
                    required
                >

                    <option value="">
                        Select Academic Term
                    </option>

                    <?php foreach (
                        $terms
                        as $term
                    ): ?>

                        <option
                            value="<?= (int)$term["id"] ?>"
                            <?= $term_id ===
                                (int)$term["id"]
                                ? "selected"
                                : "" ?>
                        >

                            <?= h(
                                $term["academic_year"]
                            ) ?>

                            —

                            <?= h(
                                $term["term_name"]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- SUBMIT -->

            <button
                type="submit"
                class="btn btn-primary"
            >
                Load Reports
            </button>

        </form>

    </section>


    <?php if (
        $class_id > 0 &&
        $term_id > 0
    ): ?>


        <!-- =============================================
             SELECTED PERIOD
        ============================================== -->

        <div class="completion-panel">

            <div class="completion-top">

                <strong>

                    <?= h(
                        $selectedClass["class_name"]
                        ?? "Selected Class"
                    ) ?>

                    &nbsp;·&nbsp;

                    <?= h(
                        $selectedTerm["academic_year"]
                        ?? ""
                    ) ?>

                    &nbsp;·&nbsp;

                    <?= h(
                        $selectedTerm["term_name"]
                        ?? ""
                    ) ?>

                </strong>


                <span>

                    <?= number_format(
                        $completionPercentage,
                        1
                    ) ?>%

                    complete

                </span>

            </div>


            <div class="progress">

                <div
                    class="progress-bar"
                    style="
                        width:
                        <?= min(
                            100,
                            max(
                                0,
                                $completionPercentage
                            )
                        ) ?>%;
                    "
                ></div>

            </div>

        </div>


        <!-- =============================================
             SUMMARY CARDS
        ============================================== -->

        <section class="summary-grid">


            <div class="summary-card">

                <span class="summary-card-label">
                    Students
                </span>

                <div class="summary-card-number">
                    <?= number_format(
                        $totalStudents
                    ) ?>
                </div>

                <div class="summary-card-sub">
                    Active students
                </div>

            </div>


            <div class="summary-card">

                <span class="summary-card-label">
                    Draft
                </span>

                <div class="summary-card-number">
                    <?= number_format(
                        $draftReports
                    ) ?>
                </div>

                <div class="summary-card-sub">
                    Reports requiring review
                </div>

            </div>


            <div class="summary-card">

                <span class="summary-card-label">
                    Approved
                </span>

                <div class="summary-card-number">
                    <?= number_format(
                        $approvedReports
                    ) ?>
                </div>

                <div class="summary-card-sub">
                    Ready for publication
                </div>

            </div>


            <div class="summary-card">

                <span class="summary-card-label">
                    Published
                </span>

                <div class="summary-card-number">
                    <?= number_format(
                        $publishedReports
                    ) ?>
                </div>

                <div class="summary-card-sub">
                    Official reports
                </div>

            </div>


            <div class="summary-card">

                <span class="summary-card-label">
                    Not Started
                </span>

                <div class="summary-card-number">
                    <?= number_format(
                        $missingReports
                    ) ?>
                </div>

                <div class="summary-card-sub">
                    No report record
                </div>

            </div>

        </section>


        <!-- =============================================
             REPORT TABLE
        ============================================== -->

        <section class="reports-panel">


            <div class="reports-header">

                <div>

                    <h3>
                        <?= h(
                            $selectedClass["class_name"]
                            ?? "Class"
                        ) ?>

                        — Student Reports
                    </h3>

                    <p>

                        <?= h(
                            $selectedTerm["academic_year"]
                            ?? ""
                        ) ?>

                        ·

                        <?= h(
                            $selectedTerm["term_name"]
                            ?? ""
                        ) ?>

                    </p>

                </div>


                <div class="quick-actions">

                    <button
                        type="button"
                        class="btn btn-light"
                        onclick="window.print()"
                    >
                        Print List
                    </button>

                </div>

            </div>


            <?php if (
                count($students) > 0
            ): ?>

                <div class="table-scroll">

                    <table class="reports-table">

                        <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Student
                            </th>

                            <th>
                                Average
                            </th>

                            <th>
                                Position
                            </th>

                            <th>
                                Attendance
                            </th>

                            <th>
                                Results
                            </th>

                            <th>
                                Report Details
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                        </thead>


                        <tbody>


                        <?php

                        $number = 1;

                        ?>


                        <?php foreach (
                            $students
                            as $student
                        ): ?>


                            <tr>


                                <!-- NUMBER -->

                                <td>

                                    <?= $number++ ?>

                                </td>


                                <!-- STUDENT -->

                                <td>

                                    <div
                                        class="student-cell"
                                    >


                                        <?php if (
                                            !empty(
                                                $student["photo"]
                                            )
                                        ): ?>

                                            <img
                                                src="../uploads/students/<?= h(
                                                    $student["photo"]
                                                ) ?>"
                                                class="student-photo"
                                                alt="Student photo"
                                            >

                                        <?php else: ?>

                                            <div
                                                class="student-photo-placeholder"
                                            >
                                                NO PHOTO
                                            </div>

                                        <?php endif; ?>


                                        <div>

                                            <div
                                                class="student-name"
                                            >

                                                <?= h(
                                                    $student["full_name"]
                                                ) ?>

                                            </div>


                                            <span
                                                class="student-id"
                                            >

                                                <?= h(
                                                    $student["student_id"]
                                                ) ?>

                                            </span>

                                        </div>

                                    </div>

                                </td>


                                <!-- AVERAGE -->

                                <td>

                                    <?php if (
                                        $student["has_result"]
                                    ): ?>

                                        <span
                                            class="score"
                                        >

                                            <?= number_format(
                                                (float)$student["average_score"],
                                                2
                                            ) ?>%

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="mini-status danger"
                                        >
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- POSITION -->

                                <td>

                                    <?php if (
                                        $student["position"]
                                        !== null
                                    ): ?>

                                        <span
                                            class="position"
                                        >

                                            <?= (int)(
                                                $student["position"]
                                            ) ?>

                                            /
                                            <?= (int)(
                                                $student["class_size"]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <!-- ATTENDANCE -->

                                <td>

                                    <?php if (
                                        $student["report_id"]
                                    ): ?>

                                        <?= number_format(
                                            $student[
                                                "attendance_percentage"
                                            ],
                                            1
                                        ) ?>%

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <!-- RESULTS -->

                                <td>

                                    <?= resultStatus(
                                        $student
                                    ) ?>

                                </td>


                                <!-- DETAILS -->

                                <td>

                                    <?= detailsStatus(
                                        $student
                                    ) ?>

                                </td>


                                <!-- REPORT STATUS -->

                                <td>

                                    <?= reportStatusBadge(
                                        $student[
                                            "report_status"
                                        ],
                                        !empty(
                                            $student["report_id"]
                                        )
                                    ) ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div
                                        class="student-actions"
                                    >


                                        <!-- EDIT DETAILS -->

                                        <a
                                            href="report_details.php?student_id=<?= (int)$student["id"] ?>&class_id=<?= $class_id ?>&term_id=<?= $term_id ?>"
                                            class="btn btn-light"
                                        >
                                            Edit Details
                                        </a>


                                        <!-- VIEW -->

                                        <a
                                            href="../student_report.php?student_id=<?= (int)$student["id"] ?>&class_id=<?= $class_id ?>&term_id=<?= $term_id ?>"
                                            class="btn btn-primary"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            View
                                        </a>


                                        <!-- PRINT -->

                                        <a
                                            href="../student_report.php?student_id=<?= (int)$student["id"] ?>&class_id=<?= $class_id ?>&term_id=<?= $term_id ?>&print=1"
                                            class="btn btn-gold"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Print
                                        </a>


                                        <?php if (
                                            !$student["report_id"]
                                            ||
                                            $student[
                                                "report_status"
                                            ] === "Draft"
                                        ): ?>


                                            <!-- APPROVE -->

                                            <?php if (
                                                $student["has_result"]
                                            ): ?>

                                                <a
                                                    href="approve_report.php?student_id=<?= (int)$student["id"] ?>&class_id=<?= $class_id ?>&term_id=<?= $term_id ?>"
                                                    class="btn btn-primary"
                                                    onclick="
                                                        return confirm(
                                                            'Approve this report?'
                                                        );
                                                    "
                                                >
                                                    Approve
                                                </a>

                                            <?php endif; ?>


                                        <?php elseif (
                                            $student[
                                                "report_status"
                                            ] === "Approved"
                                        ): ?>


                                            <!-- PUBLISH -->

                                            <a
                                                href="publish_report.php?student_id=<?= (int)$student["id"] ?>&class_id=<?= $class_id ?>&term_id=<?= $term_id ?>"
                                                class="btn btn-gold"
                                                onclick="
                                                    return confirm(
                                                        'Publish this report? Published reports should no longer be edited.'
                                                    );
                                                "
                                            >
                                                Publish
                                            </a>


                                        <?php else: ?>


                                            <!-- PUBLISHED -->

                                            <span
                                                class="mini-status success"
                                            >
                                                Official

                                            </span>


                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty-state">

                    <div class="empty-state-icon">
                        📄
                    </div>

                    <h3>
                        No Students Found
                    </h3>

                    <p>
                        There are no active students
                        assigned to this class.
                    </p>

                </div>


            <?php endif; ?>


        </section>


    <?php else: ?>


        <!-- =============================================
             INITIAL STATE
        ============================================== -->

        <section class="reports-panel">

            <div class="empty-state">

                <div class="empty-state-icon">
                    📚
                </div>

                <h3>
                    Select a Class and Term
                </h3>

                <p>
                    Choose the academic class and term
                    above to load the students and manage
                    their HIBS report cards.
                </p>

            </div>

        </section>


    <?php endif; ?>


</div>

</main>


</body>

</html>
