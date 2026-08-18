<?php

require_once "../config/db.php";
require_once "../config/auth.php";

require_student();

$userId =
    (int)$_SESSION["user_id"];


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
        s.date_of_birth,
        c.class_name

    FROM students s

    LEFT JOIN classes c
        ON c.id = s.class_id

    WHERE s.user_id = ?

    LIMIT 1
");

$stmt->execute([
    $userId
]);

$student =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$student) {
    exit("Student profile not found.");
}


/*
|--------------------------------------------------------------------------
| REPORT ID
|--------------------------------------------------------------------------
*/

$reportId =
    filter_input(
        INPUT_GET,
        "report_id",
        FILTER_VALIDATE_INT
    );


if (!$reportId) {
    exit("Invalid report.");
}


/*
|--------------------------------------------------------------------------
| REPORT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        r.*,

        ay.academic_year,

        t.term_name,

        c.class_name

    FROM report_card_records r

    INNER JOIN academic_years ay
        ON ay.id = r.academic_year_id

    INNER JOIN terms t
        ON t.id = r.term_id

    INNER JOIN classes c
        ON c.id = r.class_id

    WHERE

        r.id = ?

        AND r.student_id = ?

        AND r.report_status = 'Published'

    LIMIT 1
");

$stmt->execute([
    $reportId,
    $student["id"]
]);

$report =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$report) {

    http_response_code(403);

    exit(
        "This report is not available."
    );
}


/*
|--------------------------------------------------------------------------
| SUBJECT RESULTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        rr.*,

        s.subject_name

    FROM report_card_results rr

    INNER JOIN subjects s
        ON s.id = rr.subject_id

    WHERE rr.report_id = ?

    ORDER BY s.subject_name ASC
");

$stmt->execute([
    $reportId
]);

$results =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>
HIBS Student Report
</title>

<style>

@page {
    size: A4;
    margin: 15mm;
}

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #ffffff;

    color: #222;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 12px;

}

.report {

    max-width: 800px;

    margin: auto;

}

.school-header {

    text-align: center;

    border-bottom:
        2px solid
        #263238;

    padding-bottom: 14px;

    margin-bottom: 18px;

}

.school-name {

    font-size: 23px;

    font-weight: bold;

}

.school-subtitle {

    margin-top: 5px;

    font-size: 11px;

    letter-spacing: 1px;

}

.report-title {

    margin-top: 12px;

    font-size: 17px;

    font-weight: bold;

}

.info {

    display: grid;

    grid-template-columns:
        1fr
        1fr;

    gap: 7px;

    margin-bottom: 18px;

}

.info div {

    padding: 8px;

    border:
        1px solid
        #ddd;

}

.label {

    color: #777;

    font-size: 9px;

    text-transform: uppercase;

}

.value {

    margin-top: 3px;

    font-weight: bold;

}

table {

    width: 100%;

    border-collapse: collapse;

}

th {

    background: #263238;

    color: #fff;

    padding: 9px;

    text-align: left;

    font-size: 10px;

}

td {

    padding: 8px;

    border:
        1px solid
        #ddd;

}

.summary {

    margin-top: 18px;

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 8px;

}

.summary-box {

    padding: 12px;

    border:
        1px solid
        #ddd;

    text-align: center;

}

.summary-label {

    color: #777;

    font-size: 9px;

}

.summary-value {

    margin-top: 5px;

    font-size: 17px;

    font-weight: bold;

}

.comment {

    margin-top: 18px;

    border:
        1px solid
        #ddd;

    padding: 12px;

}

.comment-title {

    font-weight: bold;

    margin-bottom: 7px;

}

.signatures {

    margin-top: 45px;

    display: grid;

    grid-template-columns:
        1fr
        1fr;

    gap: 60px;

}

.signature {

    border-top:
        1px solid
        #333;

    padding-top: 7px;

    text-align: center;

    font-size: 10px;

}

.no-print {

    text-align: center;

    margin: 20px;

}

.print-button {

    padding: 10px 18px;

    border: 0;

    background: #263238;

    color: white;

    cursor: pointer;

}

@media print {

    .no-print {
        display: none;
    }

}

</style>

</head>


<body>


<div class="no-print">

<button
    onclick="window.print()"
    class="print-button"
>
    Print Report
</button>

</div>


<div class="report">


<div class="school-header">

    <div class="school-name">

        HILLTOP INTERNATIONAL
        BRITISH SCHOOL

    </div>

    <div class="school-subtitle">

        CAMBRIDGE INTERNATIONAL SCHOOL

    </div>

    <div class="report-title">

        STUDENT REPORT

    </div>

</div>


<div class="info">


<div>

    <div class="label">
        Student
    </div>

    <div class="value">

        <?= h(
            $student["first_name"]
            . " "
            . $student["middle_name"]
            . " "
            . $student["last_name"]
        ) ?>

    </div>

</div>


<div>

    <div class="label">
        Student ID
    </div>

    <div class="value">

        <?= h(
            $student["student_id"]
        ) ?>

    </div>

</div>


<div>

    <div class="label">
        Class
    </div>

    <div class="value">

        <?= h(
            $report["class_name"]
        ) ?>

    </div>

</div>


<div>

    <div class="label">
        Academic Year
    </div>

    <div class="value">

        <?= h(
            $report["academic_year"]
        ) ?>

    </div>

</div>


<div>

    <div class="label">
        Term
    </div>

    <div class="value">

        <?= h(
            $report["term_name"]
        ) ?>

    </div>

</div>


<div>

    <div class="label">
        Report Status
    </div>

    <div class="value">

        PUBLISHED

    </div>

</div>


</div>


<table>

<thead>

<tr>

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
    Remark
</th>

</tr>

</thead>


<tbody>

<?php foreach (
    $results
    as $result
): ?>

<tr>

<td>

    <?= h(
        $result[
            "subject_name"
        ]
    ) ?>

</td>

<td>

    <?= number_format(
        (float)$result[
            "score"
        ],
        2
    ) ?>

</td>

<td>

    <?= h(
        $result[
            "grade"
        ]
    ) ?>

</td>

<td>

    <?= h(
        $result[
            "remark"
        ]
        ?? ""
    ) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>


<div class="summary">


<div class="summary-box">

    <div class="summary-label">
        Average
    </div>

    <div class="summary-value">

        <?= number_format(
            (float)$report[
                "average_score"
            ],
            2
        ) ?>%

    </div>

</div>


<div class="summary-box">

    <div class="summary-label">
        Position
    </div>

    <div class="summary-value">

        <?= h(
            $report[
                "position"
            ]
            ?? "—"
        ) ?>

    </div>

</div>


<div class="summary-box">

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


</div>


<div class="comment">

    <div class="comment-title">
        Class Teacher's Comment
    </div>

    <?= nl2br(
        h(
            $report[
                "teacher_comment"
            ]
            ?? ""
        )
    ) ?>

</div>


<div class="comment">

    <div class="comment-title">
        Headteacher's Comment
    </div>

    <?= nl2br(
        h(
            $report[
                "headteacher_comment"
            ]
            ?? ""
        )
    ) ?>

</div>


<div class="signatures">


<div class="signature">

    Class Teacher

</div>


<div class="signature">

    Headteacher

</div>


</div>


</div>


</body>

</html>
