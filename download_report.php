<?php

session_start();

require_once "config/db.php";
require_once "vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;


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

    exit(
        "Invalid report information."
    );
}


/*
|--------------------------------------------------------------------------
| ADMIN CHECK
|--------------------------------------------------------------------------
*/

$isAdmin =
    isset($_SESSION["user_id"]) &&
    (
        ($_SESSION["role"] ?? "") === "admin"
    );


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

    exit(
        "Student not found."
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

    exit(
        "Academic term not found."
    );
}


/*
|--------------------------------------------------------------------------
| REPORT
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

    exit(
        "Report card not found."
    );
}


/*
|--------------------------------------------------------------------------
| ONLY PUBLISHED REPORTS
|--------------------------------------------------------------------------
|
| Admin can download draft reports for checking.
|
*/

$status =
    $report["report_status"]
    ?? "Draft";


if (
    $status !== "Published"
    &&
    !$isAdmin
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


/*
|--------------------------------------------------------------------------
| STUDENT NAME
|--------------------------------------------------------------------------
*/

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


if ($daysOpened > 0) {

    $attendancePercentage =
        (
            $daysPresent /
            $daysOpened
        ) * 100;
}


/*
|--------------------------------------------------------------------------
| SUMMARY
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
| GRADE
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
| REMARK
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

$photoData = null;


if (
    !empty(
        $student["photo"]
    )
) {

    $possiblePaths = [

        __DIR__
        . "/uploads/students/"
        . $student["photo"],

        __DIR__
        . "/uploads/"
        . $student["photo"],

        __DIR__
        . "/assets/uploads/students/"
        . $student["photo"]

    ];


    foreach (
        $possiblePaths
        as $photoFile
    ) {

        if (
            is_file(
                $photoFile
            )
        ) {

            $mime =
                mime_content_type(
                    $photoFile
                );

            $photoData =
                "data:"
                . $mime
                . ";base64,"
                . base64_encode(
                    file_get_contents(
                        $photoFile
                    )
                );

            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/

ob_start();

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<style>

@page {
    size: A4;
    margin: 12mm;
}

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    color: #272324;

    font-family:
        DejaVu Serif,
        serif;

    font-size: 9px;

}

.school-header {

    text-align: center;

    border-bottom:
        3px double #641c2b;

    padding-bottom: 10px;

}

.school-name {

    margin: 0;

    color: #641c2b;

    font-size: 21px;

    font-weight: bold;

    text-transform: uppercase;

}

.school-subtitle {

    margin-top: 4px;

    color: #51464a;

    font-family:
        DejaVu Sans,
        sans-serif;

    font-size: 8px;

    font-weight: bold;

    letter-spacing: 1px;

}

.school-motto {

    margin-top: 5px;

    color: #806e75;

    font-size: 8px;

    font-style: italic;

}

.report-title {

    text-align: center;

    margin: 12px 0;

}

.report-title h2 {

    margin: 0;

    color: #641c2b;

    font-size: 15px;

    text-transform: uppercase;

}

.report-title p {

    margin-top: 4px;

    color: #6f6267;

    font-family:
        DejaVu Sans,
        sans-serif;

    font-size: 8px;

}

.student-profile {

    width: 100%;

    border:
        1px solid #d9d0ca;

    border-collapse: collapse;

    margin-bottom: 12px;

}

.student-profile td {

    padding: 7px;

    border:
        1px solid #e5dfda;

    vertical-align: middle;

}

.student-photo {

    width: 80px;

    height: 95px;

    object-fit: cover;

    border:
        1px solid #641c2b;

}

.photo-placeholder {

    width: 80px;

    height: 95px;

    border:
        1px solid #cfc4bc;

    text-align: center;

    padding-top: 38px;

    color: #998d88;

    font-family:
        DejaVu Sans,
        sans-serif;

    font-size: 7px;

}

.label {

    display: block;

    color: #806f75;

    font-family:
        DejaVu Sans,
        sans-serif;

    font-size: 6px;

    font-weight: bold;

    text-transform: uppercase;

    margin-bottom: 3px;

}

.value {

    color: #31272a;

    font-family:
        DejaVu Sans,
        sans-serif;

    font-size: 8px;

    font-weight: bold;

}

.section-heading {

    padding: 6px 8px;

    margin-top: 9px;

    background: #641c2b;

    color: #fff;

    font-family:
        DejaVu Sans,
        sans-serif;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

}

.results-table {

    width: 100%;

    border-collapse: collapse;

    font-family:
        DejaVu Sans,
        sans-serif;

}

.results-table th {

    padding: 6px 4px;

    background: #f2ede8;

    border:
        1px solid #cfc6bf;

    color: #4d4146;

    font-size: 6px;

    text-align: center;

    text-transform: uppercase;

}

.results-table td {

    padding: 6px 4px;

    border:
        1px solid #d9d0ca;

    color: #3c3336;

    font-size: 7px;

    text-align: center;

}

.results-table td.subject {

    text-align: left;

    font-weight: bold;

}

.summary-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 9px;

    font-family:
        DejaVu Sans,
        sans-serif;

}

.summary-table td {

    width: 25%;

    padding: 8px;

    border:
        1px solid #d9d0ca;

    text-align: center;

}

.summary-label {

    display: block;

    color: #806f75;

    font-size: 6px;

    font-weight: bold;

    text-transform: uppercase;

}

.summary-value {

    display: block;

    margin-top: 4px;

    color: #641c2b;

    font-size: 12px;

    font-weight: bold;

}

.attendance-table {

    width: 100%;

    border-collapse: collapse;

    font-family:
        DejaVu Sans,
        sans-serif;

}

.attendance-table td {

    padding: 7px;

    border:
        1px solid #d9d0ca;

    text-align: center;

    font-size: 7px;

}

.attendance-label {

    background: #f2ede8;

    color: #806f75;

    font-size: 6px !important;

    font-weight: bold;

    text-transform: uppercase;

}

.comment {

    border:
        1px solid #d9d0ca;

    margin-top: 7px;

    font-family:
        DejaVu Sans,
        sans-serif;

}

.comment-title {

    padding: 6px 8px;

    background: #f2ede8;

    color: #641c2b;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}

.comment-text {

    min-height: 35px;

    padding: 7px;

    color: #433a3d;

    font-size: 7px;

    line-height: 1.5;

}

.promotion {

    margin-top: 8px;

    padding: 8px;

    border:
        1px solid #641c2b;

    background: #faf6f3;

    text-align: center;

    font-family:
        DejaVu Sans,
        sans-serif;

}

.promotion-label {

    color: #806f75;

    font-size: 6px;

    text-transform: uppercase;

}

.promotion-value {

    margin-top: 3px;

    color: #641c2b;

    font-size: 10px;

    font-weight: bold;

}

.signatures {

    width: 100%;

    margin-top: 25px;

    border-collapse: collapse;

}

.signatures td {

    width: 33.33%;

    text-align: center;

    padding: 5px 15px;

}

.signature-line {

    height: 22px;

    border-bottom:
        1px solid #51464a;

}

.signature-name {

    margin-top: 4px;

    font-family:
        DejaVu Sans,
        sans-serif;

    font-size: 6px;

    font-weight: bold;

    text-transform: uppercase;

}

.signature-role {

    margin-top: 2px;

    color: #80767a;

    font-family:
        DejaVu Sans,
        sans-serif;

    font-size: 6px;

}

.footer {

    border-top:
        1px solid #d9d0ca;

    margin-top: 15px;

    padding-top: 6px;

    color: #80767a;

    font-family:
        DejaVu Sans,
        sans-serif;

    font-size: 5.5px;

}

</style>

</head>

<body>


<!-- =====================================================
     SCHOOL HEADER
====================================================== -->

<div class="school-header">

    <div class="school-name">

        Hilltop International British School

    </div>

    <div class="school-subtitle">

        CAMBRIDGE INTERNATIONAL SCHOOL

    </div>

    <div class="school-motto">

        Excellence • Character • Global Citizenship

    </div>

</div>


<!-- =====================================================
     REPORT TITLE
====================================================== -->

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


<!-- =====================================================
     STUDENT INFORMATION
====================================================== -->

<table class="student-profile">

<tr>

<td>

    <span class="label">
        Student Name
    </span>

    <span class="value">
        <?= h(
            $studentName
        ) ?>
    </span>

</td>

<td>

    <span class="label">
        Student ID
    </span>

    <span class="value">
        <?= h(
            $student["student_id"]
        ) ?>
    </span>

</td>

<td>

    <span class="label">
        Class
    </span>

    <span class="value">
        <?= h(
            $student["class_name"]
        ) ?>
    </span>

</td>

<td
    rowspan="3"
    style="
        width:95px;
        text-align:center;
    "
>

<?php if ($photoData): ?>

    <img
        src="<?= $photoData ?>"
        class="student-photo"
    >

<?php else: ?>

    <div class="photo-placeholder">

        STUDENT<br>
        PHOTO

    </div>

<?php endif; ?>

</td>

</tr>


<tr>

<td>

    <span class="label">
        Academic Year
    </span>

    <span class="value">
        <?= h(
            $term["academic_year"]
        ) ?>
    </span>

</td>

<td>

    <span class="label">
        Term
    </span>

    <span class="value">
        <?= h(
            $term["term_name"]
        ) ?>
    </span>

</td>

<td>

    <span class="label">
        Gender
    </span>

    <span class="value">
        <?= h(
            $student["gender"]
            ?? "—"
        ) ?>
    </span>

</td>

</tr>


<tr>

<td colspan="3">

    <span class="label">
        Report Status
    </span>

    <span class="value">
        <?= h(
            $status
        ) ?>
    </span>

</td>

</tr>

</table>


<!-- =====================================================
     RESULTS
====================================================== -->

<div class="section-heading">

    Academic Performance

</div>


<table class="results-table">

<thead>

<tr>

<th>#</th>

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

<?php

$number = 1;

foreach (
    $results
    as $result
):


$subjectName =
    $result["subject_name"]
    ??
    $result["subject"]
    ??
    $result["name"]
    ??
    "Subject";


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


$grade =
    $result["grade"]
    ??
    getGrade(
        $score
    );


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


<?php if (
    count($results) === 0
): ?>

<tr>

<td colspan="5">

    No subject results recorded.

</td>

</tr>

<?php endif; ?>


</tbody>

</table>


<!-- =====================================================
     SUMMARY
====================================================== -->

<table class="summary-table">

<tr>

<td>

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

</td>


<td>

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

<?php else: ?>

    —

<?php endif; ?>

</span>

</td>


<td>

<span class="summary-label">
    Class Size
</span>

<span class="summary-value">

<?= $classSize !== null
    ? h($classSize)
    : "—"
?>

</span>

</td>


<td>

<span class="summary-label">
    Overall Grade
</span>

<span class="summary-value">

<?= getGrade(
    $averageScore
) ?>

</span>

</td>

</tr>

</table>


<!-- =====================================================
     ATTENDANCE
====================================================== -->

<div class="section-heading">

    Attendance Record

</div>


<table class="attendance-table">

<tr>

<td class="attendance-label">
    Days Opened
</td>

<td>
    <?= $daysOpened ?>
</td>

<td class="attendance-label">
    Present
</td>

<td>
    <?= $daysPresent ?>
</td>

<td class="attendance-label">
    Absent
</td>

<td>
    <?= $daysAbsent ?>
</td>

<td class="attendance-label">
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


<!-- =====================================================
     CONDUCT
====================================================== -->

<div class="section-heading">

    Conduct & Personal Development

</div>


<div class="comment">

    <div class="comment-title">

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


<!-- =====================================================
     TEACHER COMMENT
====================================================== -->

<div class="comment">

    <div class="comment-title">

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


<!-- =====================================================
     HEADTEACHER COMMENT
====================================================== -->

<div class="comment">

    <div class="comment-title">

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


<!-- =====================================================
     PROMOTION
====================================================== -->

<div class="promotion">

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


<!-- =====================================================
     SIGNATURES
====================================================== -->

<table class="signatures">

<tr>

<td>

    <div class="signature-line"></div>

    <div class="signature-name">
        Class Teacher
    </div>

    <div class="signature-role">
        Signature
    </div>

</td>


<td>

    <div class="signature-line"></div>

    <div class="signature-name">
        Headteacher
    </div>

    <div class="signature-role">
        Signature
    </div>

</td>


<td>

    <div class="signature-line"></div>

    <div class="signature-name">
        Parent / Guardian
    </div>

    <div class="signature-role">
        Acknowledgement
    </div>

</td>

</tr>

</table>


<!-- =====================================================
     FOOTER
====================================================== -->

<div class="footer">

    Hilltop International British School
    • Official Academic Report

    &nbsp;&nbsp;|&nbsp;&nbsp;

    Academic Year:
    <?= h(
        $term["academic_year"]
    ) ?>

    &nbsp;&nbsp;|&nbsp;&nbsp;

    Term:
    <?= h(
        $term["term_name"]
    ) ?>

</div>


</body>

</html>

<?php

$html =
    ob_get_clean();


/*
|--------------------------------------------------------------------------
| DOMPDF
|--------------------------------------------------------------------------
*/

$options =
    new Options();


$options->set(
    "isHtml5ParserEnabled",
    true
);


$options->set(
    "isRemoteEnabled",
    true
);


$options->set(
    "defaultFont",
    "DejaVu Sans"
);


$dompdf =
    new Dompdf(
        $options
    );


$dompdf->loadHtml(
    $html
);


$dompdf->setPaper(
    "A4",
    "portrait"
);


$dompdf->render();


/*
|--------------------------------------------------------------------------
| FILE NAME
|--------------------------------------------------------------------------
*/

$safeStudentName =
    preg_replace(
        "/[^A-Za-z0-9_-]/",
        "_",
        $studentName
    );


$fileName =
    "HIBS_Report_"
    . $safeStudentName
    . "_"
    . $term["term_name"]
    . "_"
    . $term["academic_year"]
    . ".pdf";


/*
|--------------------------------------------------------------------------
| DOWNLOAD
|--------------------------------------------------------------------------
*/

$dompdf->stream(
    $fileName,
    [
        "Attachment" => true
    ]
);

exit;
