<?php

session_start();

require_once "config/db.php";


/*
|--------------------------------------------------------------------------
| ADMIN / TEACHER ACCESS
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (
    !in_array(
        $_SESSION["role"],
        ["admin", "teacher"]
    )
) {
    die("Access denied.");
}


/*
|--------------------------------------------------------------------------
| PARAMETERS
|--------------------------------------------------------------------------
*/

$student_id = (int)($_GET["student_id"] ?? 0);
$class_id   = (int)($_GET["class_id"] ?? 0);
$term_id    = (int)($_GET["term_id"] ?? 0);

if (
    $student_id <= 0 ||
    $class_id <= 0 ||
    $term_id <= 0
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
        s.id,
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.gender,

        c.class_name,
        c.class_level

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

if (!$term) {
    die("Term not found.");
}


/*
|--------------------------------------------------------------------------
| STUDENT OVERALL RESULT
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

$overall = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| SUBJECT RESULTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        sr.total_score,
        sr.grade,
        sr.grade_description,
        sr.remark,

        s.subject_code,
        s.subject_name

    FROM subject_results sr

    INNER JOIN subjects s
        ON s.id = sr.subject_id

    WHERE
        sr.student_id = ?
        AND sr.class_id = ?
        AND sr.term_id = ?

    ORDER BY
        s.subject_name
");

$stmt->execute([
    $student_id,
    $class_id,
    $term_id
]);

$subjectResults = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| REPORT INFORMATION
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

$reportInfo = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$daysOpened =
    $reportInfo["days_opened"] ?? 0;

$daysPresent =
    $reportInfo["days_present"] ?? 0;

$daysAbsent =
    $reportInfo["days_absent"] ?? 0;

$conduct =
    $reportInfo["conduct"] ?? "Not recorded";

$teacherComment =
    $reportInfo["teacher_comment"] ??
    "No teacher comment has been entered.";

$headteacherComment =
    $reportInfo["headteacher_comment"] ??
    "No headteacher comment has been entered.";

$promotionStatus =
    $reportInfo["promotion_status"] ??
    "Not yet determined";


/*
|--------------------------------------------------------------------------
| ATTENDANCE %
|--------------------------------------------------------------------------
*/

$attendancePercentage = 0;

if ($daysOpened > 0) {

    $attendancePercentage =
        ($daysPresent / $daysOpened) * 100;
}


/*
|--------------------------------------------------------------------------
| POSITION
|--------------------------------------------------------------------------
*/

$positionText = "Not calculated";

if ($overall) {

    $position = (int)$overall["position"];
    $classSize = (int)$overall["class_size"];

    if ($position > 0) {

        $suffix = "th";

        if (
            $position % 100 < 11 ||
            $position % 100 > 13
        ) {

            switch ($position % 10) {

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
| PRINT MODE
|--------------------------------------------------------------------------
*/

$printMode = isset($_GET["print"]);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    HIBS Report Card -
    <?= htmlspecialchars(
        $student["first_name"]
    ) ?>
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #e9e4dd;
    color: #241b1d;
    font-family: Georgia, "Times New Roman", serif;
}

.report-wrapper {
    max-width: 1000px;
    margin: 35px auto;
}

.report-card {
    background: #fffdf9;
    padding: 45px;
    border: 1px solid #d7cec3;
    box-shadow: 0 8px 30px rgba(0,0,0,.08);
}

.school-header {
    text-align: center;
    border-bottom: 3px double #641c2b;
    padding-bottom: 22px;
    margin-bottom: 25px;
}

.school-logo {
    width: 78px;
    height: 78px;
    border: 2px solid #641c2b;
    border-radius: 50%;
    margin: 0 auto 12px;

    display: flex;
    align-items: center;
    justify-content: center;

    color: #641c2b;
    font-size: 40px;
    font-weight: bold;
}

.school-header h1 {
    margin: 0;
    color: #641c2b;
    font-size: 28px;
    letter-spacing: 1px;
}

.school-header h2 {
    margin: 7px 0;
    font-size: 17px;
    font-weight: normal;
}

.school-header p {
    margin: 4px 0;
    font-family: Arial, sans-serif;
    font-size: 11px;
    color: #6d6662;
}

.report-title {
    margin-top: 18px;
    color: #b58a3a;
    font-size: 20px;
    letter-spacing: 2px;
}

.student-information {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border: 1px solid #d9d0c6;
    margin-bottom: 25px;
}

.info-item {
    padding: 12px 15px;
    border-bottom: 1px solid #e4ddd5;
}

.info-item:nth-child(odd) {
    border-right: 1px solid #e4ddd5;
}

.info-label {
    display: block;
    font-family: Arial, sans-serif;
    font-size: 9px;
    color: #817873;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 4px;
}

.info-value {
    font-size: 14px;
    color: #30141b;
}

.section-title {
    color: #641c2b;
    font-size: 16px;
    border-bottom: 1px solid #b58a3a;
    padding-bottom: 7px;
    margin: 25px 0 10px;
}

.results-table {
    width: 100%;
    border-collapse: collapse;
}

.results-table th {
    background: #641c2b;
    color: white;
    padding: 10px 8px;
    font-family: Arial, sans-serif;
    font-size: 10px;
    text-transform: uppercase;
}

.results-table td {
    border: 1px solid #ded7ce;
    padding: 10px 8px;
    font-size: 12px;
}

.results-table tr:nth-child(even) {
    background: #f8f4ee;
}

.text-center {
    text-align: center;
}

.performance-box {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    margin-top: 18px;
    border: 1px solid #d9d0c6;
}

.performance-item {
    padding: 18px;
    text-align: center;
    border-right: 1px solid #d9d0c6;
}

.performance-item:last-child {
    border-right: none;
}

.performance-label {
    display: block;
    font-family: Arial, sans-serif;
    font-size: 9px;
    color: #817873;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.performance-value {
    color: #641c2b;
    font-size: 22px;
}

.attendance-table {
    width: 100%;
    border-collapse: collapse;
}

.attendance-table td {
    border: 1px solid #ded7ce;
    padding: 12px;
    text-align: center;
}

.attendance-label {
    font-family: Arial, sans-serif;
    font-size: 9px;
    text-transform: uppercase;
    color: #817873;
}

.comment-box {
    border: 1px solid #d9d0c6;
    min-height: 90px;
    padding: 15px;
    font-size: 13px;
    line-height: 1.7;
}

.comment-heading {
    color: #641c2b;
    font-size: 13px;
    margin-bottom: 8px;
}

.promotion-box {
    margin-top: 25px;
    border: 2px solid #641c2b;
    padding: 16px;
    text-align: center;
}

.promotion-box span {
    display: block;
    font-family: Arial, sans-serif;
    font-size: 9px;
    color: #817873;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.promotion-box strong {
    color: #641c2b;
    font-size: 20px;
}

.signatures {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 80px;
    margin-top: 60px;
}

.signature {
    text-align: center;
    border-top: 1px solid #555;
    padding-top: 8px;
    font-family: Arial, sans-serif;
    font-size: 10px;
}

.report-footer {
    text-align: center;
    border-top: 1px solid #d9d0c6;
    margin-top: 35px;
    padding-top: 15px;
    font-family: Arial, sans-serif;
    font-size: 9px;
    color: #817873;
}

.print-button {
    display: block;
    margin: 20px auto;
    padding: 11px 24px;
    border: none;
    background: #641c2b;
    color: white;
    cursor: pointer;
    font-family: Arial, sans-serif;
}

@media(max-width:700px) {

    .report-card {
        padding: 20px;
    }

    .student-information {
        grid-template-columns: 1fr;
    }

    .info-item:nth-child(odd) {
        border-right: none;
    }

    .performance-box {
        grid-template-columns: 1fr;
    }

    .performance-item {
        border-right: none;
        border-bottom: 1px solid #d9d0c6;
    }

    .signatures {
        grid-template-columns: 1fr;
        gap: 60px;
    }

}

@media print {

    body {
        background: white;
    }

    .report-wrapper {
        margin: 0;
        max-width: none;
    }

    .report-card {
        border: none;
        box-shadow: none;
        padding: 20px;
    }

    .print-button {
        display: none;
    }

    @page {
        size: A4;
        margin: 10mm;
    }

}

</style>

</head>

<body>


<?php if (!$printMode): ?>

    <button
        class="print-button"
        onclick="window.print()"
    >
        Print / Save as PDF
    </button>

<?php endif; ?>


<div class="report-wrapper">

<div class="report-card">


<!-- SCHOOL HEADER -->

<div class="school-header">

    <div class="school-logo">
        H
    </div>

    <h1>
        HILLTOP INTERNATIONAL BRITISH SCHOOL
    </h1>

    <h2>
        STUDENT ACADEMIC REPORT
    </h2>

    <p>
        HIBS REPORTS
    </p>

    <p class="report-title">
        <?= htmlspecialchars(
            $term["academic_year"]
        ) ?>

        ·

        <?= htmlspecialchars(
            $term["term_name"]
        ) ?>
    </p>

</div>


<!-- STUDENT INFORMATION -->

<div class="student-information">

    <div class="info-item">

        <span class="info-label">
            Student Name
        </span>

        <span class="info-value">

            <?= htmlspecialchars(
                $student["first_name"]
            ) ?>

            <?php if (
                $student["middle_name"]
            ): ?>

                <?= htmlspecialchars(
                    " " . $student["middle_name"]
                ) ?>

            <?php endif; ?>

            <?= htmlspecialchars(
                " " . $student["last_name"]
            ) ?>

        </span>

    </div>


    <div class="info-item">

        <span class="info-label">
            Student ID
        </span>

        <span class="info-value">
            <?= htmlspecialchars(
                $student["student_id"]
            ) ?>
        </span>

    </div>


    <div class="info-item">

        <span class="info-label">
            Class
        </span>

        <span class="info-value">
            <?= htmlspecialchars(
                $student["class_name"]
            ) ?>
        </span>

    </div>


    <div class="info-item">

        <span class="info-label">
            Academic Level
        </span>

        <span class="info-value">
            <?= htmlspecialchars(
                $student["class_level"] ?: "-"
            ) ?>
        </span>

    </div>


    <div class="info-item">

        <span class="info-label">
            Gender
        </span>

        <span class="info-value">
            <?= htmlspecialchars(
                $student["gender"] ?: "-"
            ) ?>
        </span>

    </div>


    <div class="info-item">

        <span class="info-label">
            Term
        </span>

        <span class="info-value">
            <?= htmlspecialchars(
                $term["term_name"]
            ) ?>
        </span>

    </div>

</div>


<!-- ACADEMIC RESULTS -->

<h3 class="section-title">
    Academic Performance
</h3>


<table class="results-table">

<thead>

<tr>

    <th>
        #
    </th>

    <th>
        Subject
    </th>

    <th>
        Code
    </th>

    <th>
        Score
    </th>

    <th>
        Grade
    </th>

    <th>
        Description
    </th>

    <th>
        Remark
    </th>

</tr>

</thead>


<tbody>

<?php $number = 1; ?>

<?php foreach (
    $subjectResults
    as $result
): ?>

<tr>

    <td class="text-center">
        <?= $number++ ?>
    </td>

    <td>
        <?= htmlspecialchars(
            $result["subject_name"]
        ) ?>
    </td>

    <td class="text-center">
        <?= htmlspecialchars(
            $result["subject_code"]
        ) ?>
    </td>

    <td class="text-center">

        <?= number_format(
            $result["total_score"],
            2
        ) ?>%

    </td>

    <td class="text-center">

        <strong>
            <?= htmlspecialchars(
                $result["grade"] ?? "-"
            ) ?>
        </strong>

    </td>

    <td>
        <?= htmlspecialchars(
            $result["grade_description"] ?? "-"
        ) ?>
    </td>

    <td>
        <?= htmlspecialchars(
            $result["remark"] ?? "-"
        ) ?>
    </td>

</tr>

<?php endforeach; ?>


<?php if (!$subjectResults): ?>

<tr>

    <td
        colspan="7"
        class="text-center"
    >
        No academic results have been calculated.
    </td>

</tr>

<?php endif; ?>

</tbody>

</table>


<!-- PERFORMANCE SUMMARY -->

<h3 class="section-title">
    Performance Summary
</h3>


<div class="performance-box">

    <div class="performance-item">

        <span class="performance-label">
            Overall Average
        </span>

        <span class="performance-value">

            <?= $overall
                ? number_format(
                    $overall["average_score"],
                    2
                ) . "%"
                : "N/A"
            ?>

        </span>

    </div>


    <div class="performance-item">

        <span class="performance-label">
            Position
        </span>

        <span class="performance-value">

            <?= htmlspecialchars(
                $positionText
            ) ?>

        </span>

    </div>


    <div class="performance-item">

        <span class="performance-label">
            Total Score
        </span>

        <span class="performance-value">

            <?= $overall
                ? number_format(
                    $overall["total_score"],
                    2
                )
                : "N/A"
            ?>

        </span>

    </div>

</div>


<!-- ATTENDANCE -->

<h3 class="section-title">
    Attendance
</h3>


<table class="attendance-table">

<tr>

    <td>

        <span class="attendance-label">
            Days School Opened
        </span>

        <br>

        <strong>
            <?= $daysOpened ?>
        </strong>

    </td>


    <td>

        <span class="attendance-label">
            Days Present
        </span>

        <br>

        <strong>
            <?= $daysPresent ?>
        </strong>

    </td>


    <td>

        <span class="attendance-label">
            Days Absent
        </span>

        <br>

        <strong>
            <?= $daysAbsent ?>
        </strong>

    </td>


    <td>

        <span class="attendance-label">
            Attendance
        </span>

        <br>

        <strong>

            <?= number_format(
                $attendancePercentage,
                1
            ) ?>%

        </strong>

    </td>

</tr>

</table>


<!-- CONDUCT -->

<h3 class="section-title">
    Conduct
</h3>


<div class="comment-box">

    <div class="comment-heading">
        Conduct Rating
    </div>

    <?= htmlspecialchars(
        $conduct
    ) ?>

</div>


<!-- TEACHER COMMENT -->

<h3 class="section-title">
    Teacher's Comment
</h3>


<div class="comment-box">

    <?= nl2br(
        htmlspecialchars(
            $teacherComment
        )
    ) ?>

</div>


<!-- HEADTEACHER COMMENT -->

<h3 class="section-title">
    Headteacher's Comment
</h3>


<div class="comment-box">

    <?= nl2br(
        htmlspecialchars(
            $headteacherComment
        )
    ) ?>

</div>


<!-- PROMOTION -->

<div class="promotion-box">

    <span>
        Promotion Status
    </span>

    <strong>
        <?= htmlspecialchars(
            $promotionStatus
        ) ?>
    </strong>

</div>


<!-- SIGNATURES -->

<div class="signatures">

    <div class="signature">

        Class Teacher

    </div>


    <div class="signature">

        Headteacher

    </div>

</div>


<div class="report-footer">

    HILLTOP INTERNATIONAL BRITISH SCHOOL

    <br>

    Official Student Academic Report

</div>


</div>

</div>

</body>

</html>
