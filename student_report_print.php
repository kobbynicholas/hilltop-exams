<?php

session_start();

require_once "config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS OFFICIAL A4 REPORT
| PRINT VERSION
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
| LOGIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"])
) {
    header("Location: login.php");
    exit;
}


$userId = (int)$_SESSION["user_id"];

$role = $_SESSION["role"] ?? "";


/*
|--------------------------------------------------------------------------
| REPORT ID
|--------------------------------------------------------------------------
*/

$reportId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);


if (!$reportId) {
    die("Invalid report.");
}


/*
|--------------------------------------------------------------------------
| LOAD REPORT
|--------------------------------------------------------------------------
*/

try {

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

            r.id = ?

            AND r.report_status = 'Published'
    ";

    $params = [$reportId];


    /*
    |--------------------------------------------------------------------------
    | STUDENT SECURITY
    |--------------------------------------------------------------------------
    */

    if ($role !== "admin") {

        $sql .= "
            AND r.student_id = ?
        ";

        $params[] = $userId;
    }


    $sql .= "
        LIMIT 1
    ";


    $stmt = $conn->prepare($sql);

    $stmt->execute($params);

    $report = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$report) {
        die("This published report is not available.");
    }


    /*
    |--------------------------------------------------------------------------
    | SUBJECT RESULTS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT

            rr.id,
            rr.score,
            rr.grade,
            rr.comment,

            s.subject_name

        FROM report_card_results rr

        INNER JOIN subjects s
            ON s.id = rr.subject_id

        WHERE rr.report_id = ?

        ORDER BY
            s.subject_name ASC
    ");

    $stmt->execute([
        $reportId
    ]);

    $results = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


} catch (Throwable $e) {

    die(
        "Unable to load the report."
    );
}


/*
|--------------------------------------------------------------------------
| STUDENT NAME
|--------------------------------------------------------------------------
*/

$studentName = trim(
    implode(
        " ",
        array_filter([
            $report["first_name"] ?? "",
            $report["middle_name"] ?? "",
            $report["last_name"] ?? ""
        ])
    )
);


/*
|--------------------------------------------------------------------------
| DATE OF BIRTH
|--------------------------------------------------------------------------
*/

$dob = "—";

if (!empty($report["dob"])) {

    $timestamp = strtotime(
        $report["dob"]
    );

    if ($timestamp) {

        $dob = date(
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

if (!empty($report["photo"])) {

    $possiblePhotos = [

        "uploads/students/" .
        $report["photo"],

        "uploads/" .
        $report["photo"],

        "assets/uploads/students/" .
        $report["photo"]

    ];


    foreach (
        $possiblePhotos
        as $photo
    ) {

        if (
            is_file(
                __DIR__ . "/" . $photo
            )
        ) {

            $photoPath = $photo;

            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| ATTENDANCE
|--------------------------------------------------------------------------
*/

$daysOpened = (int)(
    $report["days_opened"] ?? 0
);

$daysPresent = (int)(
    $report["days_present"] ?? 0
);

$daysAbsent = (int)(
    $report["days_absent"] ?? 0
);


$attendancePercentage = null;

if ($daysOpened > 0) {

    $attendancePercentage =
        (
            $daysPresent /
            $daysOpened
        ) * 100;
}


/*
|--------------------------------------------------------------------------
| POSITION
|--------------------------------------------------------------------------
*/

$position = "—";

if (
    $report["position"] !== null &&
    $report["position"] !== ""
) {

    $position =
        $report["position"];

    if (
        !empty(
            $report["class_size"]
        )
    ) {

        $position .=
            " / " .
            $report["class_size"];
    }
}


/*
|--------------------------------------------------------------------------
| AVERAGE
|--------------------------------------------------------------------------
*/

$average = "—";

if (
    $report["average_score"] !== null &&
    $report["average_score"] !== ""
) {

    $average =
        number_format(
            (float)$report["average_score"],
            2
        ) . "%";
}


/*
|--------------------------------------------------------------------------
| PUBLISHED DATE
|--------------------------------------------------------------------------
*/

$publishedDate = "—";

if (
    !empty(
        $report["published_at"]
    )
) {

    $timestamp = strtotime(
        $report["published_at"]
    );

    if ($timestamp) {

        $publishedDate =
            date(
                "d F Y",
                $timestamp
            );
    }
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

<title>

    <?= h($studentName) ?>

    -

    HIBS Academic Report

</title>


<style>

/*
|--------------------------------------------------------------------------
| HIBS A4 REPORT
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}


html,
body {

    margin: 0;
    padding: 0;

}


body {

    background: #eeeeeb;

    color: #263238;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 10px;

}


/*
|--------------------------------------------------------------------------
| PRINT TOOLBAR
|--------------------------------------------------------------------------
*/

.print-toolbar {

    width: 100%;

    padding: 12px 18px;

    background: #263238;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.print-toolbar-title {

    color: #ffffff;

    font-size: 10px;

    font-weight: bold;

    letter-spacing: .7px;

}


.print-actions {

    display: flex;

    gap: 7px;

}


.print-button,
.back-button {

    padding:
        8px 12px;

    border-radius: 3px;

    font-family: inherit;

    font-size: 8px;

    font-weight: bold;

    text-decoration: none;

    cursor: pointer;

}


.print-button {

    border: 0;

    background: #ffffff;

    color: #263238;

}


.back-button {

    border:
        1px solid
        rgba(255,255,255,.3);

    color: #ffffff;

}


/*
|--------------------------------------------------------------------------
| A4 PAGE
|--------------------------------------------------------------------------
*/

.report-page {

    width: 210mm;

    min-height: 297mm;

    margin: 25px auto;

    padding: 15mm;

    background: #ffffff;

    box-shadow:
        0 2px 14px
        rgba(0,0,0,.12);

}


/*
|--------------------------------------------------------------------------
| SCHOOL HEADER
|--------------------------------------------------------------------------
*/

.school-header {

    text-align: center;

    border-bottom:
        2px solid
        #263238;

    padding-bottom: 12px;

}


.school-name {

    font-size: 21px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .7px;

}


.school-title {

    margin-top: 5px;

    color: #546e7a;

    font-size: 10px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: 1px;

}


.school-contact {

    margin-top: 5px;

    color: #7a858a;

    font-size: 7px;

}


/*
|--------------------------------------------------------------------------
| REPORT TITLE
|--------------------------------------------------------------------------
*/

.report-heading {

    text-align: center;

    margin:
        15px 0;

}


.report-heading h1 {

    margin: 0;

    font-size: 16px;

    text-transform: uppercase;

    letter-spacing: 1px;

}


.report-heading p {

    margin:
        5px 0 0;

    color: #7a858a;

    font-size: 8px;

}


/*
|--------------------------------------------------------------------------
| STUDENT INFORMATION
|--------------------------------------------------------------------------
*/

.student-section {

    display: grid;

    grid-template-columns:
        90px
        1fr;

    gap: 14px;

}


.student-photo {

    width: 90px;

    height: 105px;

    object-fit: cover;

    border:
        1px solid
        #c7c7c2;

}


.photo-placeholder {

    width: 90px;

    height: 105px;

    border:
        1px solid
        #d5d5d0;

    background: #f2f2ef;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    color: #8b9599;

    font-size: 7px;

}


.student-table {

    width: 100%;

    border-collapse: collapse;

}


.student-table td {

    padding:
        6px 8px;

    border:
        1px solid
        #deddd8;

}


.student-table .label {

    width: 20%;

    background: #f2f3f1;

    color: #6f7b80;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.student-table .value {

    color: #37474f;

    font-size: 8px;

    font-weight: 600;

}


/*
|--------------------------------------------------------------------------
| SECTION
|--------------------------------------------------------------------------
*/

.section {

    margin-top: 15px;

}


.section-heading {

    padding:
        7px 9px;

    background: #263238;

    color: #ffffff;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .6px;

}


/*
|--------------------------------------------------------------------------
| SUBJECT RESULTS
|--------------------------------------------------------------------------
*/

.results {

    width: 100%;

    border-collapse: collapse;

}


.results th {

    padding:
        7px 6px;

    background: #eef0ef;

    border:
        1px solid
        #d7d7d2;

    color: #5f6d72;

    font-size: 7px;

    text-transform: uppercase;

}


.results td {

    padding:
        7px 6px;

    border:
        1px solid
        #deddd8;

    color: #37474f;

    font-size: 8px;

}


.results .number {

    width: 6%;

    text-align: center;

}


.results .subject {

    width: 30%;

    font-weight: 600;

}


.results .score {

    width: 15%;

    text-align: center;

}


.results .grade {

    width: 15%;

    text-align: center;

    font-weight: bold;

}


.results .comment {

    width: 34%;

}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

.summary {

    width: 100%;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    border:
        1px solid
        #deddd8;

}


.summary-item {

    padding: 10px;

    text-align: center;

    border-right:
        1px solid
        #deddd8;

}


.summary-item:last-child {

    border-right: 0;

}


.summary-label {

    color: #7a858a;

    font-size: 6px;

    font-weight: bold;

    text-transform: uppercase;

}


.summary-value {

    margin-top: 5px;

    color: #37474f;

    font-size: 13px;

    font-weight: 700;

}


/*
|--------------------------------------------------------------------------
| ATTENDANCE
|--------------------------------------------------------------------------
*/

.attendance {

    width: 100%;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    border:
        1px solid
        #deddd8;

}


.attendance-item {

    padding: 9px;

    text-align: center;

    border-right:
        1px solid
        #deddd8;

}


.attendance-item:last-child {

    border-right: 0;

}


/*
|--------------------------------------------------------------------------
| COMMENTS
|--------------------------------------------------------------------------
*/

.comment {

    margin-top: 8px;

    padding: 9px;

    border:
        1px solid
        #deddd8;

}


.comment-label {

    color: #6f7b80;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.comment-text {

    margin-top: 5px;

    min-height: 30px;

    color: #455a64;

    font-size: 8px;

    line-height: 1.6;

}


/*
|--------------------------------------------------------------------------
| PROMOTION
|--------------------------------------------------------------------------
*/

.promotion {

    margin-top: 10px;

    padding: 10px;

    border:
        1px solid
        #deddd8;

    display: flex;

    justify-content: space-between;

}


.promotion-label {

    color: #6f7b80;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.promotion-value {

    color: #37474f;

    font-size: 8px;

    font-weight: bold;

}


/*
|--------------------------------------------------------------------------
| SIGNATURES
|--------------------------------------------------------------------------
*/

.signatures {

    margin-top: 30px;

    display: grid;

    grid-template-columns:
        1fr
        1fr
        1fr;

    gap: 25px;

}


.signature {

    padding-top: 22px;

    border-top:
        1px solid
        #777;

    text-align: center;

    color: #657278;

    font-size: 7px;

}


.signature-stamp {

    height: 50px;

    border:
        1px dashed
        #aeb2b3;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #a0a6a8;

    font-size: 6px;

    text-transform: uppercase;

}


/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.footer {

    margin-top: 18px;

    padding-top: 9px;

    border-top:
        1px solid
        #d5d4cf;

    text-align: center;

    color: #8b9498;

    font-size: 6px;

    line-height: 1.6;

}


/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

@page {

    size: A4 portrait;

    margin: 0;

}


@media print {

    html,
    body {

        background: #ffffff;

    }


    .print-toolbar {

        display: none;

    }


    .report-page {

        width: 210mm;

        min-height: 297mm;

        margin: 0;

        padding: 15mm;

        box-shadow: none;

    }

}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media(max-width:800px) {

    .report-page {

        width: 100%;

        min-height: auto;

        margin: 0;

        padding: 20px;

    }


    .student-section {

        grid-template-columns: 1fr;

    }


    .student-photo,
    .photo-placeholder {

        margin: auto;

    }

}


@media(max-width:550px) {

    .print-toolbar {

        align-items: flex-start;

        flex-direction: column;

        gap: 10px;

    }


    .print-actions {

        width: 100%;

    }


    .print-button,
    .back-button {

        flex: 1;

        text-align: center;

    }


    .summary,
    .attendance {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .summary-item:nth-child(2),
    .attendance-item:nth-child(2) {

        border-right: 0;

    }


    .summary-item,
    .attendance-item {

        border-bottom:
            1px solid
            #deddd8;

    }


    .signatures {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     PRINT TOOLBAR
====================================================== -->

<div class="print-toolbar">


    <div class="print-toolbar-title">

        HIBS OFFICIAL ACADEMIC REPORT

    </div>


    <div class="print-actions">


        <?php if (
            $role === "admin"
        ): ?>

            <a
                href="admin/reports.php"
                class="back-button"
            >
                ← Back
            </a>

        <?php else: ?>

            <a
                href="student/reports.php"
                class="back-button"
            >
                ← My Reports
            </a>

        <?php endif; ?>


        <button
            type="button"
            class="print-button"
            onclick="window.print();"
        >
            Print / Save PDF
        </button>


    </div>


</div>


<!-- =====================================================
     A4 REPORT
====================================================== -->

<main class="report-page">


    <!-- SCHOOL HEADER -->

    <header class="school-header">


        <div class="school-name">

            Hilltop International British School

        </div>


        <div class="school-title">

            Official Academic Report

        </div>


        <div class="school-contact">

            HIBS Student Reporting System

        </div>


    </header>


    <!-- REPORT TITLE -->

    <section class="report-heading">


        <h1>

            Student Academic Report

        </h1>


        <p>

            Academic Year:

            <strong>
                <?= h(
                    $report["academic_year"]
                ) ?>
            </strong>

            &nbsp;&nbsp;|&nbsp;&nbsp;

            Term:

            <strong>
                <?= h(
                    $report["term_name"]
                ) ?>
            </strong>

        </p>


    </section>


    <!-- STUDENT INFORMATION -->

    <section class="student-section">


        <?php if (
            $photoPath
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


        <table class="student-table">


            <tr>

                <td class="label">
                    Student Name
                </td>

                <td class="value">

                    <?= h(
                        $studentName
                    ) ?>

                </td>

                <td class="label">
                    Student ID
                </td>

                <td class="value">

                    <?= h(
                        $report["student_number"]
                    ) ?>

                </td>

            </tr>


            <tr>

                <td class="label">
                    Class
                </td>

                <td class="value">

                    <?= h(
                        $report["class_name"]
                    ) ?>

                </td>

                <td class="label">
                    Gender
                </td>

                <td class="value">

                    <?= h(
                        $report["gender"]
                        ?? "—"
                    ) ?>

                </td>

            </tr>


            <tr>

                <td class="label">
                    Date of Birth
                </td>

                <td class="value">

                    <?= h($dob) ?>

                </td>

                <td class="label">
                    Report Status
                </td>

                <td class="value">

                    Published

                </td>

            </tr>


        </table>


    </section>


    <!-- ACADEMIC PERFORMANCE -->

    <section class="section">


        <div class="section-heading">

            Academic Performance

        </div>


        <table class="results">


            <thead>

            <tr>

                <th>
                    #
                </th>

                <th>
                    Subject
                </th>

                <th>
                    Score
                </th>

                <th>
                    Grade
                </th>

                <th>
                    Teacher Comment
                </th>

            </tr>

            </thead>


            <tbody>


            <?php if (
                count($results) > 0
            ): ?>


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


                        <td class="subject">

                            <?= h(
                                $result[
                                    "subject_name"
                                ]
                            ) ?>

                        </td>


                        <td class="score">

                            <?php if (
                                $result["score"]
                                !== null
                            ): ?>

                                <?= number_format(
                                    (float)$result["score"],
                                    2
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td class="grade">

                            <?= h(
                                $result["grade"]
                                ?? "—"
                            ) ?>

                        </td>


                        <td class="comment">

                            <?= h(
                                $result["comment"]
                                ?? "—"
                            ) ?>

                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php else: ?>


                <tr>

                    <td
                        colspan="5"
                        style="
                            text-align:center;
                            padding:18px;
                        "
                    >

                        No subject results recorded.

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>


        </table>


    </section>


    <!-- ACADEMIC SUMMARY -->

    <section class="section">


        <div class="section-heading">

            Academic Summary

        </div>


        <div class="summary">


            <div class="summary-item">

                <div class="summary-label">
                    Overall Average
                </div>

                <div class="summary-value">

                    <?= h(
                        $average
                    ) ?>

                </div>

            </div>


            <div class="summary-item">

                <div class="summary-label">
                    Position
                </div>

                <div class="summary-value">

                    <?= h(
                        $position
                    ) ?>

                </div>

            </div>


            <div class="summary-item">

                <div class="summary-label">
                    Class Size
                </div>

                <div class="summary-value">

                    <?= h(
                        $report["class_size"]
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
                        $report["conduct"]
                        ?? "—"
                    ) ?>

                </div>

            </div>


        </div>


    </section>


    <!-- ATTENDANCE -->

    <section class="section">


        <div class="section-heading">

            Attendance

        </div>


        <div class="attendance">


            <div class="attendance-item">

                <div class="summary-label">
                    Days Opened
                </div>

                <div class="summary-value">

                    <?= $daysOpened ?>

                </div>

            </div>


            <div class="attendance-item">

                <div class="summary-label">
                    Present
                </div>

                <div class="summary-value">

                    <?= $daysPresent ?>

                </div>

            </div>


            <div class="attendance-item">

                <div class="summary-label">
                    Absent
                </div>

                <div class="summary-value">

                    <?= $daysAbsent ?>

                </div>

            </div>


            <div class="attendance-item">

                <div class="summary-label">
                    Attendance %
                </div>

                <div class="summary-value">

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


    <!-- SCHOOL COMMENTS -->

    <section class="section">


        <div class="section-heading">

            School Comments

        </div>


        <div class="comment">


            <div class="comment-label">

                Class Teacher's Comment

            </div>


            <div class="comment-text">

                <?= nl2br(
                    h(
                        $report[
                            "teacher_comment"
                        ]
                        ??
                        "No comment provided."
                    )
                ) ?>

            </div>


        </div>


        <div class="comment">


            <div class="comment-label">

                Headteacher's Comment

            </div>


            <div class="comment-text">

                <?= nl2br(
                    h(
                        $report[
                            "headteacher_comment"
                        ]
                        ??
                        "No comment provided."
                    )
                ) ?>

            </div>


        </div>


    </section>


    <!-- PROMOTION -->

    <div class="promotion">


        <div class="promotion-label">

            Promotion Status

        </div>


        <div class="promotion-value">

            <?= h(
                $report[
                    "promotion_status"
                ]
                ??
                "—"
            ) ?>

        </div>


    </div>


    <!-- SIGNATURES -->

    <section class="signatures">


        <div class="signature">

            Class Teacher

        </div>


        <div class="signature">

            Headteacher

        </div>


        <div>

            <div class="signature-stamp">

                Official School Stamp

            </div>

        </div>


    </section>


    <!-- FOOTER -->

    <footer class="footer">

        This is an official academic report issued by
        Hilltop International British School.

        <br>

        Report published:
        <?= h(
            $publishedDate
        ) ?>

        <br>

        This report is generated from the HIBS
        School Reporting System.

    </footer>


</main>


</body>

</html>
