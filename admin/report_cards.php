<?php

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| REPORT CARD MANAGEMENT
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
|
| Do not display database errors to users.
|
*/

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
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
| SESSION FLASH MESSAGE
|--------------------------------------------------------------------------
*/

if (
    !empty(
        $_SESSION["report_message"]
    )
) {

    $message =
        $_SESSION["report_message"];

    $messageType =
        $_SESSION["report_message_type"]
        ?? "success";


    unset(
        $_SESSION["report_message"],
        $_SESSION["report_message_type"]
    );
}


/*
|--------------------------------------------------------------------------
| URL SUCCESS MESSAGES
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
| SELECTED CLASS
|--------------------------------------------------------------------------
*/

$class_id = filter_input(
    INPUT_GET,
    "class_id",
    FILTER_VALIDATE_INT
);

$class_id =
    $class_id ?: 0;


/*
|--------------------------------------------------------------------------
| SELECTED TERM
|--------------------------------------------------------------------------
*/

$term_id = filter_input(
    INPUT_GET,
    "term_id",
    FILTER_VALIDATE_INT
);

$term_id =
    $term_id ?: 0;


/*
|--------------------------------------------------------------------------
| CLASSES
|--------------------------------------------------------------------------
*/

$classes = [];

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

    $message =
        "Unable to load classes.";

    $messageType =
        "error";
}


/*
|--------------------------------------------------------------------------
| TERMS
|--------------------------------------------------------------------------
*/

$terms = [];

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
            t.id ASC
    ");

    $terms =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $message =
        "Unable to load academic terms.";

    $messageType =
        "error";
}


/*
|--------------------------------------------------------------------------
| SELECTED CLASS
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
| SELECTED TERM
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
| STUDENTS
|--------------------------------------------------------------------------
*/

$students = [];


/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS
|--------------------------------------------------------------------------
*/

$totalStudents = 0;
$notStarted = 0;
$draftReports = 0;
$approvedReports = 0;
$publishedReports = 0;
$completedReports = 0;


/*
|--------------------------------------------------------------------------
| LOAD REPORT DATA
|--------------------------------------------------------------------------
*/

if (
    $class_id > 0 &&
    $term_id > 0 &&
    $selectedClass &&
    $selectedTerm
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | STUDENTS
        |--------------------------------------------------------------------------
        |
        | We deliberately do not require a "status" column here.
        | This makes the page compatible with the existing HIBS
        | student table.
        |
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

                c.class_name,

                r.id AS report_id,

                r.report_status,

                r.days_opened,
                r.days_present,
                r.days_absent,

                r.conduct,

                r.teacher_comment,
                r.headteacher_comment,

                r.promotion_status,

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


        /*
        |--------------------------------------------------------------------------
        | PROCESS STUDENTS
        |--------------------------------------------------------------------------
        */

        foreach (
            $students
            as &$student
        ) {

            $totalStudents++;


            /*
            |--------------------------------------------------------------------------
            | FULL NAME
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


            /*
            |--------------------------------------------------------------------------
            | REPORT STATUS
            |--------------------------------------------------------------------------
            */

            $status =
                $student["report_status"]
                ?? null;


            if (
                !$student["report_id"]
            ) {

                $student["display_status"] =
                    "Not Started";

                $notStarted++;

            } elseif (
                $status === "Approved"
            ) {

                $student["display_status"] =
                    "Approved";

                $approvedReports++;

            } elseif (
                $status === "Published"
            ) {

                $student["display_status"] =
                    "Published";

                $publishedReports++;

            } else {

                $student["display_status"] =
                    "Draft";

                $draftReports++;
            }


            /*
            |--------------------------------------------------------------------------
            | RESULT CHECK
            |--------------------------------------------------------------------------
            */

            $student["has_result"] =
                $student["average_score"] !== null;


            /*
            |--------------------------------------------------------------------------
            | REPORT DETAIL CHECK
            |--------------------------------------------------------------------------
            */

            $student["has_details"] =
                (
                    $student["report_id"] !== null
                    &&
                    $student["days_opened"] !== null
                    &&
                    $student["days_present"] !== null
                    &&
                    trim(
                        (string)(
                            $student["conduct"]
                            ?? ""
                        )
                    ) !== ""
                    &&
                    trim(
                        (string)(
                            $student["promotion_status"]
                            ?? ""
                        )
                    ) !== ""
                );


            /*
            |--------------------------------------------------------------------------
            | COMPLETE REPORT
            |--------------------------------------------------------------------------
            */

            if (
                $student["has_result"]
                &&
                $student["has_details"]
            ) {

                $completedReports++;
            }


            /*
            |--------------------------------------------------------------------------
            | ATTENDANCE
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


            if (
                $opened > 0
            ) {

                $student["attendance_percentage"] =
                    (
                        $present /
                        $opened
                    ) * 100;

            } else {

                $student["attendance_percentage"] =
                    0;
            }
        }

        unset($student);


    } catch (PDOException $e) {

        $students = [];

        $message =
            "Unable to load the report card information.";

        $messageType =
            "error";
    }
}


/*
|--------------------------------------------------------------------------
| COMPLETION
|--------------------------------------------------------------------------
*/

$completionPercentage = 0;

if (
    $totalStudents > 0
) {

    $completionPercentage =
        (
            $completedReports /
            $totalStudents
        ) * 100;
}


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

function statusClass(
    string $status
): string {

    switch ($status) {

        case "Approved":
            return "approved";

        case "Published":
            return "published";

        case "Draft":
            return "draft";

        default:
            return "missing";
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
| DETAIL STATUS
|--------------------------------------------------------------------------
*/

function detailStatus(
    array $student
): string {

    if (
        !$student["report_id"]
    ) {

        return '
            <span class="mini-status danger">
                Not Started
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
    width: 100%;
    max-width: 1450px;
    margin: 0 auto;
}


/* =========================================================
   FILTER PANEL
========================================================= */

.filter-panel {
    background: #fffdf9;

    border: 1px solid #ded5cb;

    padding: 24px;

    margin-bottom: 22px;

    box-shadow:
        0 4px 16px
        rgba(50, 35, 30, .04);
}

.filter-heading {
    margin-bottom: 20px;
}

.filter-heading h3 {
    margin: 0;

    color: #641c2b;

    font-size: 18px;

    font-weight: normal;
}

.filter-heading p {
    margin: 6px 0 0;

    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 11px;
}

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

    color: #6f6560;

    font-family: Arial, sans-serif;

    font-size: 10px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .7px;
}

.filter-field select {
    width: 100%;

    height: 44px;

    padding: 8px 12px;

    border: 1px solid #d7cec5;

    background: #ffffff;

    color: #332c2d;

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
    background: #ffffff;

    border: 1px solid #ded5cb;

    padding: 18px;

    position: relative;

    overflow: hidden;
}

.summary-card::after {
    content: "";

    position: absolute;

    width: 70px;
    height: 70px;

    top: -30px;
    right: -25px;

    border-radius: 50%;

    background: #f4ede5;
}

.summary-label {
    display: block;

    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 9px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .8px;

    margin-bottom: 8px;

    position: relative;

    z-index: 1;
}

.summary-number {
    color: #641c2b;

    font-size: 26px;

    font-weight: bold;

    position: relative;

    z-index: 1;
}

.summary-description {
    margin-top: 5px;

    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 10px;

    position: relative;

    z-index: 1;
}


/* =========================================================
   COMPLETION BAR
========================================================= */

.completion-panel {
    background: #ffffff;

    border: 1px solid #ded5cb;

    padding: 18px;

    margin-bottom: 22px;
}

.completion-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 10px;

    margin-bottom: 9px;
}

.completion-header strong {
    color: #641c2b;

    font-family: Arial, sans-serif;

    font-size: 12px;
}

.completion-header span {
    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 11px;
}

.progress {
    width: 100%;

    height: 9px;

    background: #eee7df;

    overflow: hidden;
}

.progress-bar {
    height: 100%;

    background: #641c2b;

    transition: width .3s ease;
}


/* =========================================================
   REPORT PANEL
========================================================= */

.report-panel {
    background: #ffffff;

    border: 1px solid #ded5cb;

    overflow: hidden;
}

.report-panel-header {
    padding: 20px 22px;

    border-bottom: 1px solid #e8e1da;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.report-panel-header h3 {
    margin: 0;

    color: #641c2b;

    font-size: 18px;

    font-weight: normal;
}

.report-panel-header p {
    margin: 5px 0 0;

    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 11px;
}


/* =========================================================
   TABLE
========================================================= */

.table-container {
    width: 100%;

    overflow-x: auto;
}

.report-table {
    width: 100%;

    min-width: 1300px;

    border-collapse: collapse;
}

.report-table th {
    padding: 12px 10px;

    background: #641c2b;

    color: #ffffff;

    font-family: Arial, sans-serif;

    font-size: 9px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .5px;

    text-align: left;

    white-space: nowrap;
}

.report-table td {
    padding: 12px 10px;

    border-bottom: 1px solid #eee8e1;

    color: #514a47;

    font-family: Arial, sans-serif;

    font-size: 11px;

    vertical-align: middle;
}

.report-table tbody tr:hover td {
    background: #fcfaf7;
}


/* =========================================================
   STUDENT
========================================================= */

.student-cell {
    display: flex;

    align-items: center;

    gap: 10px;

    min-width: 230px;
}

.student-photo {
    width: 43px;
    height: 51px;

    object-fit: cover;

    border: 1px solid #641c2b;

    background: #f4eee8;

    flex-shrink: 0;
}

.no-photo {
    width: 43px;
    height: 51px;

    display: flex;

    align-items: center;

    justify-content: center;

    border: 1px solid #641c2b;

    background: #f4eee8;

    color: #817873;

    font-size: 7px;

    text-align: center;

    flex-shrink: 0;
}

.student-name {
    color: #3c292d;

    font-weight: bold;

    line-height: 1.4;
}

.student-id {
    display: block;

    margin-top: 2px;

    color: #817873;

    font-size: 9px;

    font-weight: normal;
}


/* =========================================================
   STATUS
========================================================= */

.status-badge {
    display: inline-block;

    padding: 6px 9px;

    font-family: Arial, sans-serif;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .5px;

    white-space: nowrap;
}

.status-missing {
    background: #f5e7e5;

    color: #91382f;
}

.status-draft {
    background: #ece8e4;

    color: #665d58;
}

.status-approved {
    background: #f4ecd8;

    color: #775c1f;
}

.status-published {
    background: #e4eee7;

    color: #315e40;
}


/* =========================================================
   MINI STATUS
========================================================= */

.mini-status {
    display: inline-block;

    font-family: Arial, sans-serif;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    white-space: nowrap;
}

.mini-status.success {
    color: #337248;
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

    font-weight: bold;
}

.position {
    color: #3f3033;

    font-weight: bold;
}

.muted {
    color: #99908a;
}


/* =========================================================
   ACTIONS
========================================================= */

.action-container {
    display: flex;

    flex-wrap: wrap;

    align-items: center;

    gap: 5px;

    min-width: 300px;
}

.action-container form {
    display: inline;
    margin: 0;
    padding: 0;
}

.action-container .btn {
    padding: 7px 9px;

    font-size: 9px;

    white-space: nowrap;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-state {
    padding: 70px 25px;

    text-align: center;
}

.empty-icon {
    font-size: 42px;

    margin-bottom: 12px;
}

.empty-state h3 {
    margin: 0;

    color: #641c2b;

    font-weight: normal;
}

.empty-state p {
    max-width: 500px;

    margin: 8px auto 0;

    color: #817873;

    font-family: Arial, sans-serif;

    font-size: 11px;

    line-height: 1.6;
}


/* =========================================================
   HEADER ACTION
========================================================= */

.header-actions {
    display: flex;

    flex-wrap: wrap;

    gap: 7px;
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
    .header-actions,
    .action-container {
        display: none !important;
    }

    .page {
        padding: 0 !important;
        margin: 0 !important;
    }

    .report-panel {
        border: none;
    }

    .report-table {
        min-width: 0;
    }

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 1100px) {

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

    .report-panel-header {
        flex-direction: column;

        align-items: flex-start;
    }

}

@media(max-width: 500px) {

    .summary-grid {
        grid-template-columns: 1fr;
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
         PAGE TITLE
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
         MESSAGE
    ================================================== -->

    <?php if (
        $message !== ""
    ): ?>

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
         FILTER
    ================================================== -->

    <section class="filter-panel">

        <div class="filter-heading">

            <h3>
                Select Reporting Period
            </h3>

            <p>
                Select a class and academic term
                to manage the student report cards.
            </p>

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


            <!-- LOAD -->

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
        $term_id > 0 &&
        $selectedClass &&
        $selectedTerm
    ): ?>


        <!-- =================================================
             COMPLETION
        ================================================== -->

        <section class="completion-panel">

            <div class="completion-header">

                <strong>

                    <?= h(
                        $selectedClass["class_name"]
                    ) ?>

                    &nbsp;·&nbsp;

                    <?= h(
                        $selectedTerm["academic_year"]
                    ) ?>

                    &nbsp;·&nbsp;

                    <?= h(
                        $selectedTerm["term_name"]
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

        </section>


        <!-- =================================================
             SUMMARY
        ================================================== -->

        <section class="summary-grid">


            <div class="summary-card">

                <span class="summary-label">
                    Students
                </span>

                <div class="summary-number">
                    <?= number_format(
                        $totalStudents
                    ) ?>
                </div>

                <div class="summary-description">
                    Students in class
                </div>

            </div>


            <div class="summary-card">

                <span class="summary-label">
                    Not Started
                </span>

                <div class="summary-number">
                    <?= number_format(
                        $notStarted
                    ) ?>
                </div>

                <div class="summary-description">
                    No report record
                </div>

            </div>


            <div class="summary-card">

                <span class="summary-label">
                    Draft
                </span>

                <div class="summary-number">
                    <?= number_format(
                        $draftReports
                    ) ?>
                </div>

                <div class="summary-description">
                    Reports requiring review
                </div>

            </div>


            <div class="summary-card">

                <span class="summary-label">
                    Approved
                </span>

                <div class="summary-number">
                    <?= number_format(
                        $approvedReports
                    ) ?>
                </div>

                <div class="summary-description">
                    Ready to publish
                </div>

            </div>


            <div class="summary-card">

                <span class="summary-label">
                    Published
                </span>

                <div class="summary-number">
                    <?= number_format(
                        $publishedReports
                    ) ?>
                </div>

                <div class="summary-description">
                    Official reports
                </div>

            </div>

        </section>


        <!-- =================================================
             REPORT TABLE
        ================================================== -->

        <section class="report-panel">


            <div class="report-panel-header">

                <div>

                    <h3>

                        <?= h(
                            $selectedClass["class_name"]
                        ) ?>

                        — Report Cards

                    </h3>

                    <p>

                        <?= h(
                            $selectedTerm["academic_year"]
                        ) ?>

                        &nbsp;·&nbsp;

                        <?= h(
                            $selectedTerm["term_name"]
                        ) ?>

                    </p>

                </div>


                <div class="header-actions">

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


                <div class="table-container">

                    <table class="report-table">


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

                        $counter = 1;

                        ?>


                        <?php foreach (
                            $students
                            as $student
                        ): ?>


                            <tr>


                                <!-- NUMBER -->

                                <td>

                                    <?= $counter++ ?>

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
                                                alt="Student photograph"
                                            >

                                        <?php else: ?>

                                            <div
                                                class="no-photo"
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
                                                (float)$student[
                                                    "average_score"
                                                ],
                                                2
                                            ) ?>%

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="muted"
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

                                        <span
                                            class="muted"
                                        >
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ATTENDANCE -->

                                <td>

                                    <?php if (
                                        $student["report_id"]
                                    ): ?>

                                        <?= number_format(
                                            (float)$student[
                                                "attendance_percentage"
                                            ],
                                            1
                                        ) ?>%

                                    <?php else: ?>

                                        <span
                                            class="muted"
                                        >
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- RESULTS -->

                                <td>

                                    <?= resultStatus(
                                        $student
                                    ) ?>

                                </td>


                                <!-- REPORT DETAILS -->

                                <td>

                                    <?= detailStatus(
                                        $student
                                    ) ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="
                                            status-badge
                                            status-<?=
                                                h(
                                                    statusClass(
                                                        $student[
                                                            "display_status"
                                                        ]
                                                    )
                                                )
                                            ?>
                                        "
                                    >

                                        <?= h(
                                            $student[
                                                "display_status"
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div
                                        class="action-container"
                                    >


                                        <!-- EDIT DETAILS -->

                                        <a
                                            href="report_details.php?student_id=<?= (int)$student["id"] ?>&class_id=<?= (int)$class_id ?>&term_id=<?= (int)$term_id ?>"
                                            class="btn btn-light"
                                        >
                                            Edit Details
                                        </a>


                                        <!-- VIEW REPORT -->

                                        <a
                                            href="../student_report.php?student_id=<?= (int)$student["id"] ?>&class_id=<?= (int)$class_id ?>&term_id=<?= (int)$term_id ?>"
                                            class="btn btn-primary"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            View
                                        </a>


                                        <!-- PRINT -->

                                        <a
                                            href="../student_report.php?student_id=<?= (int)$student["id"] ?>&class_id=<?= (int)$class_id ?>&term_id=<?= (int)$term_id ?>&print=1"
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
                                                "display_status"
                                            ] === "Draft"
                                        ): ?>


                                            <!-- APPROVE -->

                                            <?php if (
                                                $student["has_result"]
                                                &&
                                                $student["has_details"]
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    action="approve_report.php"
                                                    onsubmit="
                                                        return confirm(
                                                            'Approve this report?'
                                                        );
                                                    "
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= h(
                                                            $csrfToken
                                                        ) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="student_id"
                                                        value="<?= (int)$student["id"] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="class_id"
                                                        value="<?= (int)$class_id ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="term_id"
                                                        value="<?= (int)$term_id ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-primary"
                                                    >
                                                        Approve
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                        <?php elseif (
                                            $student[
                                                "display_status"
                                            ] === "Approved"
                                        ): ?>


                                            <!-- PUBLISH -->

                                            <form
                                                method="POST"
                                                action="publish_report.php"
                                                onsubmit="
                                                    return confirm(
                                                        'Publish this report? Once published, it becomes an official HIBS report.'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= h(
                                                        $csrfToken
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="student_id"
                                                    value="<?= (int)$student["id"] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="class_id"
                                                    value="<?= (int)$class_id ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="term_id"
                                                    value="<?= (int)$term_id ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-gold"
                                                >
                                                    Publish
                                                </button>

                                            </form>


                                        <?php else: ?>


                                            <!-- PUBLISHED -->

                                            <span
                                                class="
                                                    mini-status
                                                    success
                                                "
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

                    <div class="empty-icon">
                        📄
                    </div>

                    <h3>
                        No Students Found
                    </h3>

                    <p>
                        No students were found in the
                        selected class.
                    </p>

                </div>


            <?php endif; ?>


        </section>


    <?php else: ?>


        <!-- =================================================
             INITIAL STATE
        ================================================== -->

        <section class="report-panel">

            <div class="empty-state">

                <div class="empty-icon">
                    📚
                </div>

                <h3>
                    Select a Class and Term
                </h3>

                <p>
                    Choose the class and academic term
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
