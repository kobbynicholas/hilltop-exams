<?php

session_start();

require_once "config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS OFFICIAL STUDENT REPORT
|--------------------------------------------------------------------------
*/


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
| LOGIN SECURITY
|--------------------------------------------------------------------------
*/

$userId =
    $_SESSION["student_id"]
    ??
    $_SESSION["user_id"]
    ??
    null;

$role =
    $_SESSION["role"]
    ?? "";


if (!$userId) {

    header(
        "Location: login.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| REQUESTED REPORT
|--------------------------------------------------------------------------
*/

$reportId =
    filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );


$studentId =
    filter_input(
        INPUT_GET,
        "student_id",
        FILTER_VALIDATE_INT
    );


$classId =
    filter_input(
        INPUT_GET,
        "class_id",
        FILTER_VALIDATE_INT
    );


$termId =
    filter_input(
        INPUT_GET,
        "term_id",
        FILTER_VALIDATE_INT
    );


/*
|--------------------------------------------------------------------------
| REPORT
|--------------------------------------------------------------------------
*/

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
    | BUILD REPORT QUERY
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            r.*,

            s.student_id AS student_number,

            s.first_name,
            s.middle_name,
            s.last_name,

            s.gender,
            s.dob,
            s.photo,

            c.class_name,

            t.term_name,

            ay.academic_year

        FROM report_card_records r

        INNER JOIN students s
            ON s.id = r.student_id

        INNER JOIN classes c
            ON c.id = r.class_id

        INNER JOIN terms t
            ON t.id = r.term_id

        INNER JOIN academic_years ay
            ON ay.id = t.academic_year_id

        WHERE
            r.report_status = 'Published'
    ";


    $params = [];


    /*
    |--------------------------------------------------------------------------
    | REPORT ID
    |--------------------------------------------------------------------------
    */

    if ($reportId) {

        $sql .= "
            AND r.id = ?
        ";

        $params[] =
            $reportId;

    } else {

        /*
        |--------------------------------------------------------------------------
        | STUDENT REPORT LOOKUP
        |--------------------------------------------------------------------------
        */

        if (!$studentId) {

            throw new Exception(
                "No report was selected."
            );
        }


        $sql .= "
            AND r.student_id = ?
        ";

        $params[] =
            $studentId;


        if ($classId) {

            $sql .= "
                AND r.class_id = ?
            ";

            $params[] =
                $classId;
        }


        if ($termId) {

            $sql .= "
                AND r.term_id = ?
            ";

            $params[] =
                $termId;
        }


        $sql .= "

            ORDER BY

                r.published_at DESC,
                r.id DESC

            LIMIT 1

        ";
    }


    /*
    |--------------------------------------------------------------------------
    | STUDENT SECURITY
    |--------------------------------------------------------------------------
    |
    | Administrators may preview reports.
    |
    | Students can only see their own reports.
    |
    */

    if (
        $role !== "admin"
    ) {

        $sql .= "
            AND r.student_id = ?
        ";

        $params[] =
            (int)$userId;
    }


    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    $stmt =
        $conn->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );


    $report =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$report) {

        throw new Exception(
            "This report is not available."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUBJECT RESULTS TABLE
    |--------------------------------------------------------------------------
    */

    $resultTableExists = false;


    try {

        $check =
            $conn->query("
                SHOW TABLES LIKE 'report_card_results'
            ");

        $resultTableExists =
            $check->rowCount() > 0;

    } catch (
        Throwable $e
    ) {

        $resultTableExists = false;
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD SUBJECT RESULTS
    |--------------------------------------------------------------------------
    */

    if (
        $resultTableExists
    ) {

        $stmt =
            $conn->prepare("
                SELECT

                    rr.id,

                    rr.subject_id,

                    rr.score,

                    rr.grade,

                    rr.comment,

                    s.subject_name

                FROM report_card_results rr

                INNER JOIN subjects s
                    ON s.id = rr.subject_id

                WHERE

                    rr.report_id = ?

                ORDER BY

                    s.subject_name ASC
            ");


        $stmt->execute([
            $report["id"]
        ]);


        $results =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }


} catch (
    Throwable $e
) {

    $error =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| STUDENT NAME
|--------------------------------------------------------------------------
*/

$studentName = "";

if ($report) {

    $studentName =
        trim(
            implode(
                " ",
                array_filter([
                    $report[
                        "first_name"
                    ]
                    ?? "",

                    $report[
                        "middle_name"
                    ]
                    ?? "",

                    $report[
                        "last_name"
                    ]
                    ?? ""
                ])
            )
        );
}


/*
|--------------------------------------------------------------------------
| DATE OF BIRTH
|--------------------------------------------------------------------------
*/

$formattedDob =
    "—";


if (
    $report &&
    !empty(
        $report["dob"]
    )
) {

    $timestamp =
        strtotime(
            $report["dob"]
        );


    if ($timestamp) {

        $formattedDob =
            date(
                "d M Y",
                $timestamp
            );
    }
}


/*
|--------------------------------------------------------------------------
| PHOTO
|--------------------------------------------------------------------------
*/

$photoPath = "";


if (
    $report &&
    !empty(
        $report["photo"]
    )
) {

    $possiblePhotos = [

        "uploads/students/"
        . $report["photo"],

        "uploads/"
        . $report["photo"],

        "assets/uploads/students/"
        . $report["photo"]

    ];


    foreach (
        $possiblePhotos
        as $photo
    ) {

        if (
            is_file(
                __DIR__
                . "/"
                . $photo
            )
        ) {

            $photoPath =
                $photo;

            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| ATTENDANCE
|--------------------------------------------------------------------------
*/

$daysOpened =
    (int)(
        $report[
            "days_opened"
        ]
        ?? 0
    );


$daysPresent =
    (int)(
        $report[
            "days_present"
        ]
        ?? 0
    );


$daysAbsent =
    (int)(
        $report[
            "days_absent"
        ]
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| ATTENDANCE PERCENTAGE
|--------------------------------------------------------------------------
*/

$attendancePercentage = null;


if (
    $daysOpened > 0
) {

    $attendancePercentage =
        (
            $daysPresent
            /
            $daysOpened
        )
        * 100;
}


/*
|--------------------------------------------------------------------------
| POSITION
|--------------------------------------------------------------------------
*/

$positionText =
    "—";


if (
    $report["position"]
    !== null &&
    $report["position"]
    !== ""
) {

    $positionText =
        $report["position"];


    if (
        !empty(
            $report["class_size"]
        )
    ) {

        $positionText .=
            " / "
            .
            $report["class_size"];
    }
}


/*
|--------------------------------------------------------------------------
| PRINT DATE
|--------------------------------------------------------------------------
*/

$printDate =
    date(
        "d F Y"
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

    <?= h(
        $studentName
    ) ?>

    |

    HIBS Academic Report

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

    background: #e9e8e3;

    color: #263238;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 12px;

}


/* =========================================================
   TOOLBAR
========================================================= */

.toolbar {

    width: 100%;

    padding: 14px 20px;

    background: #263238;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.toolbar-title {

    color: #ffffff;

    font-size: 11px;

    font-weight: bold;

    letter-spacing: .5px;

}


.toolbar-actions {

    display: flex;

    gap: 7px;

}


.toolbar-btn {

    display: inline-block;

    padding:
        8px 12px;

    border-radius: 3px;

    text-decoration: none;

    font-size: 9px;

    font-weight: bold;

    cursor: pointer;

}


.print-btn {

    border: 0;

    background: #ffffff;

    color: #37474f;

}


.back-btn {

    border:
        1px solid
        rgba(255,255,255,.25);

    color: #ffffff;

}


/* =========================================================
   REPORT PAPER
========================================================= */

.report-page {

    width: 210mm;

    min-height: 297mm;

    margin:
        25px auto;

    padding:
        18mm;

    background: #ffffff;

    box-shadow:
        0 2px 12px
        rgba(0,0,0,.12);

}


/* =========================================================
   SCHOOL HEADER
========================================================= */

.school-header {

    text-align: center;

    padding-bottom: 18px;

    border-bottom:
        2px solid
        #263238;

}


.school-name {

    color: #263238;

    font-size: 21px;

    font-weight: 700;

    letter-spacing: .5px;

    text-transform: uppercase;

}


.school-subtitle {

    margin-top: 6px;

    color: #546e7a;

    font-size: 10px;

    font-weight: bold;

    letter-spacing: 1px;

}


.school-address {

    margin-top: 5px;

    color: #7a858a;

    font-size: 8px;

}


/* =========================================================
   REPORT TITLE
========================================================= */

.report-title {

    margin-top: 18px;

    text-align: center;

}


.report-title h1 {

    margin: 0;

    color: #263238;

    font-size: 17px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 1px;

}


.report-title p {

    margin:
        6px 0 0;

    color: #7a858a;

    font-size: 9px;

}


/* =========================================================
   STUDENT INFORMATION
========================================================= */

.student-information {

    margin-top: 20px;

    display: grid;

    grid-template-columns:
        95px
        1fr;

    gap: 18px;

}


.student-photo {

    width: 95px;

    height: 115px;

    object-fit: cover;

    border:
        1px solid
        #c9c7c2;

}


.photo-placeholder {

    width: 95px;

    height: 115px;

    background: #f0f1ef;

    border:
        1px solid
        #d7d8d5;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #8a9498;

    text-align: center;

    font-size: 8px;

}


.student-details {

    border:
        1px solid
        #deddd8;

}


.student-row {

    min-height: 31px;

    padding:
        7px 10px;

    display: grid;

    grid-template-columns:
        125px
        1fr;

    border-bottom:
        1px solid
        #e9e8e4;

}


.student-row:last-child {
    border-bottom: 0;
}


.student-label {

    color: #7a858a;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.student-value {

    color: #37474f;

    font-size: 9px;

    font-weight: 600;

}


/* =========================================================
   SECTION TITLE
========================================================= */

.section {

    margin-top: 20px;

}


.section-title {

    padding:
        9px 10px;

    background: #263238;

    color: #ffffff;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .8px;

}


/* =========================================================
   RESULTS TABLE
========================================================= */

.results-table {

    width: 100%;

    border-collapse: collapse;

}


.results-table th {

    padding:
        8px 7px;

    background: #f0f1ef;

    border:
        1px solid
        #d8d8d3;

    color: #54636a;

    font-size: 7px;

    text-transform: uppercase;

    text-align: left;

}


.results-table td {

    padding:
        8px 7px;

    border:
        1px solid
        #deddd8;

    color: #37474f;

    font-size: 8px;

}


.results-table td.number {

    text-align: center;

}


.results-table td.grade {

    text-align: center;

    font-weight: bold;

}


.subject {

    font-weight: 600;

}


/* =========================================================
   SUMMARY
========================================================= */

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    border:
        1px solid
        #deddd8;

}


.summary-item {

    min-height: 65px;

    padding: 10px;

    border-right:
        1px solid
        #deddd8;

    text-align: center;

}


.summary-item:last-child {
    border-right: 0;
}


.summary-label {

    color: #7a858a;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.summary-value {

    margin-top: 7px;

    color: #37474f;

    font-size: 15px;

    font-weight: 700;

}


/* =========================================================
   ATTENDANCE
========================================================= */

.attendance-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    border:
        1px solid
        #deddd8;

}


.attendance-item {

    padding: 10px;

    text-align: center;

    border-right:
        1px solid
        #deddd8;

}


.attendance-item:last-child {
    border-right: 0;
}


.attendance-label {

    color: #7a858a;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.attendance-value {

    margin-top: 6px;

    color: #455a64;

    font-size: 13px;

    font-weight: 600;

}


/* =========================================================
   COMMENTS
========================================================= */

.comment-box {

    margin-top: 10px;

    padding: 12px;

    border:
        1px solid
        #deddd8;

}


.comment-heading {

    color: #68777d;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

    margin-bottom: 7px;

}


.comment-text {

    color: #455a64;

    font-size: 8px;

    line-height: 1.7;

    min-height: 25px;

}


/* =========================================================
   PROMOTION
========================================================= */

.promotion {

    margin-top: 15px;

    padding: 12px;

    border:
        1px solid
        #deddd8;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.promotion-label {

    color: #68777d;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.promotion-value {

    color: #37474f;

    font-size: 9px;

    font-weight: 700;

}


/* =========================================================
   FOOTER
========================================================= */

.report-footer {

    margin-top: 28px;

    padding-top: 12px;

    border-top:
        1px solid
        #d9d8d4;

    display: grid;

    grid-template-columns:
        1fr
        1fr
        1fr;

    gap: 20px;

}


.signature {

    padding-top: 22px;

    border-top:
        1px solid
        #8b9294;

    color: #69777c;

    font-size: 7px;

    text-align: center;

}


.footer-note {

    margin-top: 15px;

    color: #92999c;

    font-size: 7px;

    text-align: center;

    line-height: 1.6;

}


/* =========================================================
   ERROR
========================================================= */

.error-page {

    width: 100%;

    max-width: 600px;

    margin: 80px auto;

    padding: 25px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    text-align: center;

}


.error-page h2 {

    margin: 0;

    color: #455a64;

    font-size: 18px;

}


.error-page p {

    color: #7a858a;

    font-size: 10px;

    line-height: 1.7;

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

        background: #ffffff;

    }


    .toolbar {

        display: none;

    }


    .report-page {

        width: 210mm;

        min-height: 297mm;

        margin: 0;

        padding: 18mm;

        box-shadow: none;

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width:850px) {

    .report-page {

        width: 100%;

        min-height: auto;

        margin: 0;

        padding: 20px;

        box-shadow: none;

    }


    .student-information {

        grid-template-columns: 1fr;

    }


    .student-photo,
    .photo-placeholder {

        margin: auto;

    }


    .summary-grid,
    .attendance-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .summary-item:nth-child(2),
    .attendance-item:nth-child(2) {

        border-right: 0;

    }


    .report-footer {

        grid-template-columns: 1fr;

    }

}


@media(max-width:500px) {

    .toolbar {

        align-items: flex-start;

        flex-direction: column;

        gap: 10px;

    }


    .toolbar-actions {

        width: 100%;

    }


    .toolbar-btn {

        flex: 1;

        text-align: center;

    }


    .student-row {

        grid-template-columns: 1fr;

        gap: 3px;

    }


    .summary-grid,
    .attendance-grid {

        grid-template-columns: 1fr;

    }


    .summary-item,
    .attendance-item {

        border-right: 0;

        border-bottom:
            1px solid
            #deddd8;

    }


    .summary-item:last-child,
    .attendance-item:last-child {

        border-bottom: 0;

    }

}

</style>

</head>


<body>


<?php if (
    $report
): ?>


<!-- =====================================================
     TOOLBAR
====================================================== -->

<div class="toolbar">


    <div class="toolbar-title">

        HIBS OFFICIAL ACADEMIC REPORT

    </div>


    <div class="toolbar-actions">


        <?php if (
            $role === "student"
        ): ?>

            <a
                href="student/reports.php"
                class="toolbar-btn back-btn"
            >
                ← My Reports
            </a>

        <?php else: ?>

            <a
                href="admin/reports.php"
                class="toolbar-btn back-btn"
            >
                ← Reports
            </a>

        <?php endif; ?>


        <a
    href="student_report_print.php?id=<?= (int)$report["id"] ?>"
    class="toolbar-btn print-btn"
    target="_blank"
    rel="noopener"
>
    Print / Save PDF
</a>

    </div>


</div>


<!-- =====================================================
     REPORT PAPER
====================================================== -->

<main class="report-page">


    <!-- =================================================
         SCHOOL HEADER
    ================================================== -->

    <header class="school-header">


        <div class="school-name">

            Hilltop International British School

        </div>


        <div class="school-subtitle">

            HIBS

        </div>


        <div class="school-address">

            Official Academic Reporting System

        </div>


    </header>


    <!-- =================================================
         REPORT TITLE
    ================================================== -->

    <section class="report-title">


        <h1>
            Student Academic Report
        </h1>


        <p>

            <?= h(
                $report["academic_year"]
            ) ?>

            |

            <?= h(
                $report["term_name"]
            ) ?>

        </p>


    </section>


    <!-- =================================================
         STUDENT INFORMATION
    ================================================== -->

    <section class="student-information">


        <?php if (
            $photoPath !== ""
        ): ?>

            <img
                src="<?= h(
                    $photoPath
                ) ?>"
                class="student-photo"
                alt="Student photograph"
            >

        <?php else: ?>

            <div class="photo-placeholder">

                STUDENT<br>
                PHOTOGRAPH

            </div>

        <?php endif; ?>


        <div class="student-details">


            <div class="student-row">

                <div class="student-label">
                    Student Name
                </div>

                <div class="student-value">

                    <?= h(
                        $studentName
                    ) ?>

                </div>

            </div>


            <div class="student-row">

                <div class="student-label">
                    Student ID
                </div>

                <div class="student-value">

                    <?= h(
                        $report["student_number"]
                    ) ?>

                </div>

            </div>


            <div class="student-row">

                <div class="student-label">
                    Class
                </div>

                <div class="student-value">

                    <?= h(
                        $report["class_name"]
                    ) ?>

                </div>

            </div>


            <div class="student-row">

                <div class="student-label">
                    Gender
                </div>

                <div class="student-value">

                    <?= h(
                        $report["gender"]
                        ?? "—"
                    ) ?>

                </div>

            </div>


            <div class="student-row">

                <div class="student-label">
                    Date of Birth
                </div>

                <div class="student-value">

                    <?= h(
                        $formattedDob
                    ) ?>

                </div>

            </div>


        </div>


    </section>


    <!-- =================================================
         ACADEMIC RESULTS
    ================================================== -->

    <section class="section">


        <div class="section-title">

            Academic Performance

        </div>


        <?php if (
            count($results) > 0
        ): ?>


            <table class="results-table">


                <thead>

                <tr>

                    <th
                        style="width:8%;"
                    >
                        #
                    </th>

                    <th
                        style="width:32%;"
                    >
                        Subject
                    </th>

                    <th
                        style="width:15%;"
                    >
                        Score
                    </th>

                    <th
                        style="width:15%;"
                    >
                        Grade
                    </th>

                    <th>
                        Comment
                    </th>

                </tr>

                </thead>


                <tbody>


                <?php
                $number = 1;
                ?>


                <?php foreach (
                    $results
                    as $result
                ): ?>


                    <tr>

                        <td class="number">

                            <?= $number++ ?>

                        </td>


                        <td>

                            <span
                                class="subject"
                            >

                                <?= h(
                                    $result[
                                        "subject_name"
                                    ]
                                ) ?>

                            </span>

                        </td>


                        <td class="number">

                            <?php if (
                                $result[
                                    "score"
                                ] !== null
                            ): ?>

                                <?= number_format(
                                    (float)$result[
                                        "score"
                                    ],
                                    2
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td class="grade">

                            <?= h(
                                $result[
                                    "grade"
                                ]
                                ?? "—"
                            ) ?>

                        </td>


                        <td>

                            <?= h(
                                $result[
                                    "comment"
                                ]
                                ?? "—"
                            ) ?>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>


            </table>


        <?php else: ?>


            <table class="results-table">

                <tr>

                    <td
                        style="
                            text-align:center;
                            padding:20px;
                        "
                    >

                        No subject results have been
                        recorded for this report.

                    </td>

                </tr>

            </table>


        <?php endif; ?>


    </section>


    <!-- =================================================
         ACADEMIC SUMMARY
    ================================================== -->

    <section class="section">


        <div class="section-title">

            Academic Summary

        </div>


        <div class="summary-grid">


            <div class="summary-item">

                <div class="summary-label">
                    Overall Average
                </div>

                <div class="summary-value">

                    <?php if (
                        $report[
                            "average_score"
                        ]
                        !== null
                    ): ?>

                        <?= number_format(
                            (float)$report[
                                "average_score"
                            ],
                            2
                        ) ?>%

                    <?php else: ?>

                        —

                    <?php endif; ?>

                </div>

            </div>


            <div class="summary-item">

                <div class="summary-label">
                    Position
                </div>

                <div class="summary-value">

                    <?= h(
                        $positionText
                    ) ?>

                </div>

            </div>


            <div class="summary-item">

                <div class="summary-label">
                    Class Size
                </div>

                <div class="summary-value">

                    <?= h(
                        $report[
                            "class_size"
                        ]
                        ?? "—"
                    ) ?>

                </div>

            </div>


            <div class="summary-item">

                <div class="summary-label">
                    Conduct
                </div>

                <div class="summary-value">

                    <?= h(
                        $report[
                            "conduct"
                        ]
                        ?? "—"
                    ) ?>

                </div>

            </div>


        </div>


    </section>


    <!-- =================================================
         ATTENDANCE
    ================================================== -->

    <section class="section">


        <div class="section-title">

            Attendance Record

        </div>


        <div class="attendance-grid">


            <div class="attendance-item">

                <div class="attendance-label">
                    Days Opened
                </div>

                <div class="attendance-value">

                    <?= $daysOpened ?>

                </div>

            </div>


            <div class="attendance-item">

                <div class="attendance-label">
                    Days Present
                </div>

                <div class="attendance-value">

                    <?= $daysPresent ?>

                </div>

            </div>


            <div class="attendance-item">

                <div class="attendance-label">
                    Days Absent
                </div>

                <div class="attendance-value">

                    <?= $daysAbsent ?>

                </div>

            </div>


            <div class="attendance-item">

                <div class="attendance-label">
                    Attendance %
                </div>

                <div class="attendance-value">

                    <?php if (
                        $attendancePercentage
                        !== null
                    ): ?>

                        <?= number_format(
                            $attendancePercentage,
                            1
                        ) ?>%

                    <?php else: ?>

                        —

                    <?php endif; ?>

                </div>

            </div>


        </div>


    </section>


    <!-- =================================================
         COMMENTS
    ================================================== -->

    <section class="section">


        <div class="section-title">

            School Comments

        </div>


        <div class="comment-box">


            <div class="comment-heading">

                Class Teacher's Comment

            </div>


            <div class="comment-text">

                <?= nl2br(
                    h(
                        $report[
                            "teacher_comment"
                        ]
                        ?? "No comment provided."
                    )
                ) ?>

            </div>


        </div>


        <div class="comment-box">


            <div class="comment-heading">

                Headteacher's Comment

            </div>


            <div class="comment-text">

                <?= nl2br(
                    h(
                        $report[
                            "headteacher_comment"
                        ]
                        ?? "No comment provided."
                    )
                ) ?>

            </div>


        </div>


    </section>


    <!-- =================================================
         PROMOTION
    ================================================== -->

    <div class="promotion">


        <div class="promotion-label">

            Promotion Status

        </div>


        <div class="promotion-value">

            <?= h(
                $report[
                    "promotion_status"
                ]
                ?? "—"
            ) ?>

        </div>


    </div>


    <!-- =================================================
         FOOTER
    ================================================== -->

    <footer class="report-footer">


        <div class="signature">

            Class Teacher

        </div>


        <div class="signature">

            Headteacher

        </div>


        <div class="signature">

            Official School Stamp

        </div>


    </footer>


    <div class="footer-note">

        This is an official HIBS academic report.

        <br>

        Published on
        <?= h(
            $printDate
        ) ?>.

        <br>

        This document should be retained as part of
        the student's academic record.

    </div>


</main>


<?php else: ?>


<!-- =====================================================
     ERROR
====================================================== -->

<div class="error-page">


    <h2>
        Report Unavailable
    </h2>


    <p>

        <?= h(
            $error
            ?: "The requested academic report is not available."
        ) ?>

    </p>


    <?php if (
        $role === "student"
    ): ?>

        <a
            href="student/reports.php"
            class="toolbar-btn back-btn"
            style="
                display:inline-block;
                background:#455a64;
                color:#fff;
            "
        >
            Return to My Reports
        </a>

    <?php else: ?>

        <a
            href="admin/reports.php"
            class="toolbar-btn back-btn"
            style="
                display:inline-block;
                background:#455a64;
                color:#fff;
            "
        >
            Return to Reports
        </a>

    <?php endif; ?>


</div>


<?php endif; ?>


</body>

</html>
