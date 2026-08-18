<?php

session_start();

require_once "config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| OFFICIAL STUDENT REPORT
|--------------------------------------------------------------------------
|
| URL:
|
| student_report.php
|     ?student_id=1
|     &class_id=1
|     &term_id=1
|
|--------------------------------------------------------------------------
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
| INPUT
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

    http_response_code(400);

    exit(
        "Invalid report information."
    );
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$student = null;
$term = null;
$report = null;
$results = [];

$error = "";


/*
|--------------------------------------------------------------------------
| LOAD REPORT
|--------------------------------------------------------------------------
*/

try {


    /*
    |--------------------------------------------------------------------------
    | STUDENT
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
            s.dob,
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

    $student =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$student) {

        throw new Exception(
            "Student record could not be found."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TERM
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

    $term =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$term) {

        throw new Exception(
            "Academic term could not be found."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REPORT RECORD
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
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$report) {

        throw new Exception(
            "A report card has not yet been created for this student."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PUBLISHED REPORT SECURITY
    |--------------------------------------------------------------------------
    |
    | Only Published reports are official student reports.
    |
    | Administrators can still preview a report using:
    |
    | ?preview=1
    |
    */

    $isAdmin =
        isset($_SESSION["user_id"]) &&
        (
            ($_SESSION["role"] ?? "") === "admin"
        );


    $isPreview =
        isset($_GET["preview"]) &&
        $_GET["preview"] === "1";


    $status =
        $report["report_status"]
        ?? "Draft";


    if (
        $status !== "Published"
        &&
        !(
            $isAdmin &&
            $isPreview
        )
    ) {

        http_response_code(403);

        exit(
            "This report has not been published."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUBJECT RESULTS
    |--------------------------------------------------------------------------
    |
    | The report system can contain subject-level records.
    | We first try the expected student_results structure.
    |
    */

    $stmt = $conn->prepare("
        SELECT *

        FROM student_results

        WHERE

            student_id = ?

            AND class_id = ?

            AND term_id = ?

        ORDER BY id ASC
    ");

    $stmt->execute([
        $student_id,
        $class_id,
        $term_id
    ]);

    $results =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $e) {

    $error =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| FULL NAME
|--------------------------------------------------------------------------
*/

$studentName = "";

if ($student) {

    $studentName =
        trim(
            implode(
                " ",
                array_filter([
                    $student["first_name"] ?? "",
                    $student["middle_name"] ?? "",
                    $student["last_name"] ?? ""
                ])
            )
        );
}


/*
|--------------------------------------------------------------------------
| ATTENDANCE
|--------------------------------------------------------------------------
*/

$daysOpened =
    (int)(
        $report["days_opened"]
        ?? 0
    );

$daysPresent =
    (int)(
        $report["days_present"]
        ?? 0
    );

$daysAbsent =
    (int)(
        $report["days_absent"]
        ?? 0
    );


$attendancePercentage = 0;

if (
    $daysOpened > 0
) {

    $attendancePercentage =
        (
            $daysPresent /
            $daysOpened
        ) * 100;
}


/*
|--------------------------------------------------------------------------
| SUMMARY VALUES
|--------------------------------------------------------------------------
*/

$averageScore =
    $report["average_score"]
    ?? null;

$position =
    $report["position"]
    ?? null;

$classSize =
    $report["class_size"]
    ?? null;


/*
|--------------------------------------------------------------------------
| FALLBACK FROM STUDENT RESULTS
|--------------------------------------------------------------------------
*/

if (
    $averageScore === null &&
    count($results) > 0
) {

    foreach (
        $results
        as $result
    ) {

        if (
            isset(
                $result["average_score"]
            )
        ) {

            $averageScore =
                $result["average_score"];

            break;
        }


        if (
            isset(
                $result["average"]
            )
        ) {

            $averageScore =
                $result["average"];

            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| GRADE HELPER
|--------------------------------------------------------------------------
*/

function getGrade(
    $score
): string {

    if (
        $score === null ||
        $score === ""
    ) {

        return "—";
    }

    $score =
        (float)$score;


    if ($score >= 80) {
        return "A";
    }

    if ($score >= 70) {
        return "B";
    }

    if ($score >= 60) {
        return "C";
    }

    if ($score >= 50) {
        return "D";
    }

    if ($score >= 40) {
        return "E";
    }

    return "F";
}


/*
|--------------------------------------------------------------------------
| GRADE DESCRIPTION
|--------------------------------------------------------------------------
*/

function getRemark(
    $score
): string {

    if (
        $score === null ||
        $score === ""
    ) {

        return "";
    }

    $score =
        (float)$score;


    if ($score >= 80) {
        return "Excellent";
    }

    if ($score >= 70) {
        return "Very Good";
    }

    if ($score >= 60) {
        return "Good";
    }

    if ($score >= 50) {
        return "Satisfactory";
    }

    if ($score >= 40) {
        return "Needs Improvement";
    }

    return "Below Standard";
}


/*
|--------------------------------------------------------------------------
| PHOTO
|--------------------------------------------------------------------------
*/

$photoPath = "";

if (
    !empty(
        $student["photo"]
    )
) {

    $possiblePaths = [

        "uploads/students/"
        . $student["photo"],

        "uploads/"
        . $student["photo"],

        "assets/uploads/students/"
        . $student["photo"]

    ];


    foreach (
        $possiblePaths
        as $path
    ) {

        if (
            is_file(
                __DIR__
                . "/"
                . $path
            )
        ) {

            $photoPath =
                $path;

            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| PRINT MODE
|--------------------------------------------------------------------------
*/

$printMode =
    isset($_GET["print"]) &&
    $_GET["print"] === "1";

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
    HIBS Official Student Report
</title>


<style>

/* =========================================================
   HIBS OFFICIAL REPORT
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {
    background: #ece9e4;

    color: #272324;

    font-family:
        Georgia,
        "Times New Roman",
        serif;
}


/* =========================================================
   TOOLBAR
========================================================= */

.toolbar {
    width: 100%;

    padding: 14px 20px;

    background: #33252a;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    position: sticky;

    top: 0;

    z-index: 100;
}

.toolbar-title {
    color: #fff;

    font-family: Arial, sans-serif;

    font-size: 13px;

    font-weight: bold;
}

.toolbar-actions {
    display: flex;

    gap: 8px;
}

.toolbar a,
.toolbar button {
    border: 0;

    padding: 9px 13px;

    background: #fff;

    color: #33252a;

    font-family: Arial, sans-serif;

    font-size: 11px;

    font-weight: bold;

    text-decoration: none;

    cursor: pointer;
}


/* =========================================================
   REPORT PAGE
========================================================= */

.report-wrapper {
    width: 210mm;

    min-height: 297mm;

    margin: 25px auto;

    padding: 13mm;

    background: #fff;

    box-shadow:
        0 8px 30px
        rgba(0,0,0,.14);
}


/* =========================================================
   SCHOOL HEADER
========================================================= */

.school-header {
    text-align: center;

    border-bottom:
        3px double #641c2b;

    padding-bottom: 12px;

    margin-bottom: 14px;
}

.school-name {
    margin: 0;

    color: #641c2b;

    font-size: 25px;

    font-weight: bold;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.school-subtitle {
    margin-top: 4px;

    color: #51464a;

    font-family: Arial, sans-serif;

    font-size: 9px;

    letter-spacing: 1.5px;

    font-weight: bold;
}

.school-motto {
    margin-top: 7px;

    color: #806e75;

    font-size: 10px;

    font-style: italic;
}


/* =========================================================
   REPORT TITLE
========================================================= */

.report-title {
    text-align: center;

    margin: 13px 0 16px;
}

.report-title h2 {
    margin: 0;

    color: #641c2b;

    font-size: 18px;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.report-title p {
    margin: 4px 0 0;

    color: #6f6267;

    font-family: Arial, sans-serif;

    font-size: 10px;
}


/* =========================================================
   STUDENT PROFILE
========================================================= */

.student-profile {
    display: grid;

    grid-template-columns:
        1fr
        95px;

    gap: 15px;

    border:
        1px solid #d9d0ca;

    margin-bottom: 14px;
}

.student-information {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    padding: 11px;

    gap: 0;

}

.info-item {
    min-height: 43px;

    padding: 6px 9px;

    border-bottom:
        1px solid #eee9e5;
}

.info-item:nth-child(odd) {
    border-right:
        1px solid #eee9e5;
}

.info-label {
    display: block;

    margin-bottom: 4px;

    color: #806f75;

    font-family: Arial, sans-serif;

    font-size: 7px;

    font-weight: bold;

    letter-spacing: .6px;

    text-transform: uppercase;
}

.info-value {
    color: #31272a;

    font-family: Arial, sans-serif;

    font-size: 10px;

    font-weight: bold;
}

.student-photo-box {
    padding: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-left:
        1px solid #d9d0ca;

    background: #faf8f5;
}

.student-photo-box img {
    width: 76px;

    height: 92px;

    object-fit: cover;

    border:
        1px solid #641c2b;
}

.photo-placeholder {
    width: 76px;

    height: 92px;

    border:
        1px solid #cfc4bc;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    color: #998d88;

    font-family: Arial, sans-serif;

    font-size: 7px;
}


/* =========================================================
   SECTION TITLE
========================================================= */

.section-heading {
    display: flex;

    align-items: center;

    justify-content: space-between;

    background: #641c2b;

    color: #fff;

    padding: 7px 9px;

    margin-top: 12px;

    margin-bottom: 0;
}

.section-heading strong {
    font-family: Arial, sans-serif;

    font-size: 9px;

    letter-spacing: .7px;

    text-transform: uppercase;
}

.section-heading span {
    font-family: Arial, sans-serif;

    font-size: 8px;
}


/* =========================================================
   RESULTS TABLE
========================================================= */

.results-table {
    width: 100%;

    border-collapse: collapse;

    font-family: Arial, sans-serif;

    margin-bottom: 12px;
}

.results-table th {
    padding: 7px 5px;

    border:
        1px solid #cfc6bf;

    background: #f2ede8;

    color: #4d4146;

    font-size: 7px;

    font-weight: bold;

    text-align: center;

    text-transform: uppercase;
}

.results-table td {
    padding: 7px 5px;

    border:
        1px solid #d9d0ca;

    color: #3c3336;

    font-size: 8px;

    text-align: center;

    vertical-align: middle;
}

.results-table td.subject {
    text-align: left;

    font-weight: bold;
}

.results-table tbody tr:nth-child(even) {
    background: #fcfaf8;
}


/* =========================================================
   SUMMARY GRID
========================================================= */

.summary-grid {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    margin-top: 11px;

    border:
        1px solid #d9d0ca;
}

.summary-item {
    padding: 9px;

    text-align: center;

    border-right:
        1px solid #d9d0ca;
}

.summary-item:last-child {
    border-right: 0;
}

.summary-label {
    display: block;

    color: #806f75;

    font-family: Arial, sans-serif;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .4px;

    margin-bottom: 5px;
}

.summary-value {
    color: #641c2b;

    font-family: Arial, sans-serif;

    font-size: 14px;

    font-weight: bold;
}


/* =========================================================
   ATTENDANCE
========================================================= */

.attendance-table {
    width: 100%;

    border-collapse: collapse;

    font-family: Arial, sans-serif;

    margin-bottom: 12px;
}

.attendance-table td {
    padding: 8px;

    border:
        1px solid #d9d0ca;

    font-size: 8px;

    text-align: center;
}

.attendance-table .label {
    background: #f2ede8;

    color: #806f75;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;
}


/* =========================================================
   COMMENTS
========================================================= */

.comment-box {
    border:
        1px solid #d9d0ca;

    margin-bottom: 9px;

    font-family: Arial, sans-serif;
}

.comment-label {
    padding: 7px 9px;

    background: #f2ede8;

    color: #641c2b;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;
}

.comment-text {
    min-height: 45px;

    padding: 9px;

    color: #433a3d;

    font-size: 8px;

    line-height: 1.6;
}


/* =========================================================
   PROMOTION
========================================================= */

.promotion-box {
    margin-top: 10px;

    padding: 9px;

    border:
        1px solid #641c2b;

    background: #faf6f3;

    font-family: Arial, sans-serif;

    text-align: center;
}

.promotion-label {
    color: #806f75;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;
}

.promotion-value {
    margin-top: 4px;

    color: #641c2b;

    font-size: 12px;

    font-weight: bold;
}


/* =========================================================
   SIGNATURES
========================================================= */

.signature-grid {
    display: grid;

    grid-template-columns:
        1fr
        1fr
        1fr;

    gap: 20px;

    margin-top: 27px;
}

.signature {
    text-align: center;

    font-family: Arial, sans-serif;
}

.signature-line {
    height: 28px;

    border-bottom:
        1px solid #51464a;

    margin-bottom: 5px;
}

.signature-name {
    color: #40373a;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;
}

.signature-role {
    margin-top: 2px;

    color: #80767a;

    font-size: 7px;
}


/* =========================================================
   FOOTER
========================================================= */

.report-footer {
    border-top:
        1px solid #d9d0ca;

    margin-top: 20px;

    padding-top: 8px;

    display: flex;

    justify-content: space-between;

    gap: 10px;

    color: #80767a;

    font-family: Arial, sans-serif;

    font-size: 6.5px;
}

.report-reference {
    color: #641c2b;

    font-weight: bold;
}


/* =========================================================
   ERROR
========================================================= */

.error-page {
    width: 210mm;

    margin: 40px auto;

    background: #fff;

    padding: 40px;

    text-align: center;

    box-shadow:
        0 8px 30px
        rgba(0,0,0,.12);
}

.error-page h2 {
    color: #641c2b;

    font-family: Arial, sans-serif;
}

.error-page p {
    color: #665b5e;

    font-family: Arial, sans-serif;

    font-size: 13px;
}


/* =========================================================
   PRINT
========================================================= */

@page {
    size: A4;

    margin: 0;
}

@media print {

    html,
    body {
        background: #fff;
    }

    .toolbar {
        display: none !important;
    }

    .report-wrapper {
        width: 210mm;

        min-height: 297mm;

        margin: 0;

        padding: 13mm;

        box-shadow: none;
    }

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:800px) {

    .report-wrapper {
        width: 100%;

        margin: 0;

        padding: 15px;
    }

    .student-profile {
        grid-template-columns: 1fr;
    }

    .student-photo-box {
        border-left: 0;

        border-top:
            1px solid #d9d0ca;
    }

    .summary-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .signature-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<?php if ($error !== ""): ?>


    <div class="error-page">

        <h2>
            HIBS Report Unavailable
        </h2>

        <p>
            <?= h($error) ?>
        </p>

        <?php if ($isAdmin ?? false): ?>

            <p>
                This report can be viewed after the
                required report information has been
                completed and published.
            </p>

        <?php endif; ?>

    </div>


<?php else: ?>


    <?php if (!$printMode): ?>

        <div class="toolbar">

            <div class="toolbar-title">
                HIBS • Official Student Report
            </div>

            <div class="toolbar-actions">

                <a
                    href="javascript:history.back()"
                >
                    ← Back
                </a>

                <button
                    type="button"
                    onclick="window.print()"
                >
                    Print Report
                </button>

            </div>

        </div>

    <?php endif; ?>


    <main class="report-wrapper">


        <!-- =================================================
             SCHOOL HEADER
        ================================================== -->

        <header class="school-header">

            <h1 class="school-name">
                Hilltop International British School
            </h1>

            <div class="school-subtitle">
                CAMBRIDGE INTERNATIONAL SCHOOL
            </div>

            <div class="school-motto">
                Excellence • Character • Global Citizenship
            </div>

        </header>


        <!-- =================================================
             REPORT TITLE
        ================================================== -->

        <div class="report-title">

            <h2>
                Student Academic Report
            </h2>

            <p>

                <?= h(
                    $term["academic_year"]
                ) ?>

                &nbsp; • &nbsp;

                <?= h(
                    $term["term_name"]
                ) ?>

            </p>

        </div>


        <!-- =================================================
             STUDENT INFORMATION
        ================================================== -->

        <section class="student-profile">


            <div class="student-information">


                <div class="info-item">

                    <span class="info-label">
                        Student Name
                    </span>

                    <span class="info-value">
                        <?= h(
                            $studentName
                        ) ?>
                    </span>

                </div>


                <div class="info-item">

                    <span class="info-label">
                        Student ID
                    </span>

                    <span class="info-value">
                        <?= h(
                            $student["student_id"]
                        ) ?>
                    </span>

                </div>


                <div class="info-item">

                    <span class="info-label">
                        Class
                    </span>

                    <span class="info-value">
                        <?= h(
                            $student["class_name"]
                        ) ?>
                    </span>

                </div>


                <div class="info-item">

                    <span class="info-label">
                        Academic Year
                    </span>

                    <span class="info-value">
                        <?= h(
                            $term["academic_year"]
                        ) ?>
                    </span>

                </div>


                <div class="info-item">

                    <span class="info-label">
                        Term
                    </span>

                    <span class="info-value">
                        <?= h(
                            $term["term_name"]
                        ) ?>
                    </span>

                </div>


                <div class="info-item">

                    <span class="info-label">
                        Report Status
                    </span>

                    <span class="info-value">
                        <?= h(
                            $status
                        ) ?>
                    </span>

                </div>


            </div>


            <div class="student-photo-box">

                <?php if (
                    $photoPath !== ""
                ): ?>

                    <img
                        src="<?= h(
                            $photoPath
                        ) ?>"
                        alt="Student photograph"
                    >

                <?php else: ?>

                    <div class="photo-placeholder">
                        STUDENT<br>
                        PHOTO
                    </div>

                <?php endif; ?>

            </div>


        </section>


        <!-- =================================================
             ACADEMIC PERFORMANCE
        ================================================== -->

        <div class="section-heading">

            <strong>
                Academic Performance
            </strong>

            <span>
                Official Term Results
            </span>

        </div>


        <table class="results-table">

            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th style="text-align:left;">
                        Subject
                    </th>

                    <th>
                        Score
                    </th>

                    <th>
                        Grade
                    </th>

                    <th>
                        Remark
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (
                count($results) > 0
            ): ?>


                <?php

                $number = 1;

                foreach (
                    $results
                    as $result
                ):

                    /*
                    |--------------------------------------------------------------------------
                    | SUBJECT NAME
                    |--------------------------------------------------------------------------
                    */

                    $subjectName =
                        $result["subject_name"]
                        ??
                        $result["subject"]
                        ??
                        $result["name"]
                        ??
                        "Subject";


                    /*
                    |--------------------------------------------------------------------------
                    | SCORE
                    |--------------------------------------------------------------------------
                    */

                    $score = null;

                    if (
                        isset(
                            $result["total_score"]
                        )
                    ) {

                        $score =
                            $result["total_score"];

                    } elseif (
                        isset(
                            $result["score"]
                        )
                    ) {

                        $score =
                            $result["score"];

                    } elseif (
                        isset(
                            $result["average_score"]
                        )
                    ) {

                        $score =
                            $result["average_score"];
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | GRADE
                    |--------------------------------------------------------------------------
                    */

                    $grade =
                        $result["grade"]
                        ??
                        getGrade(
                            $score
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | REMARK
                    |--------------------------------------------------------------------------
                    */

                    $remark =
                        $result["remark"]
                        ??
                        getRemark(
                            $score
                        );

                ?>


                    <tr>

                        <td>
                            <?= $number++ ?>
                        </td>

                        <td class="subject">
                            <?= h(
                                $subjectName
                            ) ?>
                        </td>

                        <td>

                            <?php if (
                                $score !== null
                            ): ?>

                                <?= number_format(
                                    (float)$score,
                                    2
                                ) ?>%

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= h(
                                $grade
                            ) ?>
                        </td>

                        <td>
                            <?= h(
                                $remark
                            ) ?>
                        </td>

                    </tr>


                <?php endforeach; ?>


            <?php else: ?>


                <tr>

                    <td
                        colspan="5"
                        style="
                            padding:20px;
                            color:#806f75;
                        "
                    >

                        No subject-level results
                        have been recorded.

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>

        </table>


        <!-- =================================================
             PERFORMANCE SUMMARY
        ================================================== -->

        <div class="summary-grid">


            <div class="summary-item">

                <span class="summary-label">
                    Overall Average
                </span>

                <span class="summary-value">

                    <?php if (
                        $averageScore !== null
                    ): ?>

                        <?= number_format(
                            (float)$averageScore,
                            2
                        ) ?>%

                    <?php else: ?>

                        —

                    <?php endif; ?>

                </span>

            </div>


            <div class="summary-item">

                <span class="summary-label">
                    Position
                </span>

                <span class="summary-value">

                    <?php if (
                        $position !== null
                    ): ?>

                        <?= h(
                            $position
                        ) ?>

                        <?php if (
                            $classSize !== null
                        ): ?>

                            /
                            <?= h(
                                $classSize
                            ) ?>

                        <?php endif; ?>

                    <?php else: ?>

                        —

                    <?php endif; ?>

                </span>

            </div>


            <div class="summary-item">

                <span class="summary-label">
                    Class Size
                </span>

                <span class="summary-value">

                    <?= $classSize !== null
                        ? h($classSize)
                        : "—"
                    ?>

                </span>

            </div>


            <div class="summary-item">

                <span class="summary-label">
                    Overall Grade
                </span>

                <span class="summary-value">

                    <?= getGrade(
                        $averageScore
                    ) ?>

                </span>

            </div>


        </div>


        <!-- =================================================
             ATTENDANCE
        ================================================== -->

        <div class="section-heading">

            <strong>
                Attendance Record
            </strong>

            <span>
                Term Attendance
            </span>

        </div>


        <table class="attendance-table">

            <tr>

                <td class="label">
                    Days School Opened
                </td>

                <td>
                    <?= $daysOpened ?>
                </td>

                <td class="label">
                    Days Present
                </td>

                <td>
                    <?= $daysPresent ?>
                </td>

                <td class="label">
                    Days Absent
                </td>

                <td>
                    <?= $daysAbsent ?>
                </td>

                <td class="label">
                    Attendance
                </td>

                <td>

                    <?= number_format(
                        $attendancePercentage,
                        1
                    ) ?>%

                </td>

            </tr>

        </table>


        <!-- =================================================
             CONDUCT
        ================================================== -->

        <div class="section-heading">

            <strong>
                Conduct & Personal Development
            </strong>

        </div>


        <div class="comment-box">

            <div class="comment-label">
                Conduct
            </div>

            <div class="comment-text">

                <?= nl2br(
                    h(
                        $report["conduct"]
                        ??
                        "No conduct comment recorded."
                    )
                ) ?>

            </div>

        </div>


        <!-- =================================================
             TEACHER COMMENT
        ================================================== -->

        <div class="comment-box">

            <div class="comment-label">
                Class Teacher's Comment
            </div>

            <div class="comment-text">

                <?= nl2br(
                    h(
                        $report["teacher_comment"]
                        ??
                        "No teacher comment recorded."
                    )
                ) ?>

            </div>

        </div>


        <!-- =================================================
             HEADTEACHER COMMENT
        ================================================== -->

        <div class="comment-box">

            <div class="comment-label">
                Headteacher's Comment
            </div>

            <div class="comment-text">

                <?= nl2br(
                    h(
                        $report["headteacher_comment"]
                        ??
                        "No headteacher comment recorded."
                    )
                ) ?>

            </div>

        </div>


        <!-- =================================================
             PROMOTION
        ================================================== -->

        <div class="promotion-box">

            <div class="promotion-label">
                Promotion / Progression Status
            </div>

            <div class="promotion-value">

                <?= h(
                    $report["promotion_status"]
                    ??
                    "Not recorded"
                ) ?>

            </div>

        </div>


        <!-- =================================================
             SIGNATURES
        ================================================== -->

        <div class="signature-grid">


            <div class="signature">

                <div class="signature-line"></div>

                <div class="signature-name">
                    Class Teacher
                </div>

                <div class="signature-role">
                    Signature
                </div>

            </div>


            <div class="signature">

                <div class="signature-line"></div>

                <div class="signature-name">
                    Headteacher
                </div>

                <div class="signature-role">
                    Signature
                </div>

            </div>


            <div class="signature">

                <div class="signature-line"></div>

                <div class="signature-name">
                    Parent / Guardian
                </div>

                <div class="signature-role">
                    Acknowledgement
                </div>

            </div>


        </div>


        <!-- =================================================
             FOOTER
        ================================================== -->

        <footer class="report-footer">

            <div>

                Hilltop International British School

                <br>

                Official Academic Report

            </div>


            <div>

                Report Status:

                <span class="report-reference">

                    <?= h(
                        $status
                    ) ?>

                </span>

                <br>

                Issued:

                <?= !empty(
                    $report["published_at"]
                )
                    ? h(
                        date(
                            "d M Y",
                            strtotime(
                                $report["published_at"]
                            )
                        )
                    )
                    : "—"
                ?>

            </div>

        </footer>


    </main>


<?php endif; ?>


<?php if (
    $printMode &&
    $error === ""
): ?>

<script>

window.addEventListener(
    "load",
    function () {

        window.print();

    }
);

</script>

<?php endif; ?>


</body>

</html>
