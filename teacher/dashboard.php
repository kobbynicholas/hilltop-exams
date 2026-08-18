<?php

session_start();

require_once "../config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| TEACHER DASHBOARD
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
| TEACHER SECURITY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "teacher"
) {

    header(
        "Location: ../login.php"
    );

    exit;
}


$userId =
    (int)$_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| TEACHER PROFILE
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        t.id,

        t.employee_id,

        t.phone,

        t.qualification,

        t.specialization,

        u.first_name,

        u.last_name,

        u.email

    FROM teachers t

    INNER JOIN users u
        ON u.id = t.user_id

    WHERE t.user_id = ?

    LIMIT 1
");

$stmt->execute([
    $userId
]);

$teacher =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$teacher) {

    die(
        "Teacher profile could not be found."
    );
}


$teacherId =
    (int)$teacher["id"];


$teacherName =
    trim(
        $teacher["first_name"]
        . " "
        . $teacher["last_name"]
    );


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/


/*
| Classes
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)

    FROM teacher_classes

    WHERE teacher_id = ?
");

$stmt->execute([
    $teacherId
]);

$totalClasses =
    (int)$stmt->fetchColumn();


/*
| Subjects
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)

    FROM teacher_subjects

    WHERE teacher_id = ?
");

$stmt->execute([
    $teacherId
]);

$totalSubjects =
    (int)$stmt->fetchColumn();


/*
| Students
*/

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id)

    FROM students s

    INNER JOIN teacher_classes tc
        ON tc.class_id = s.class_id

    WHERE tc.teacher_id = ?
");

$stmt->execute([
    $teacherId
]);

$totalStudents =
    (int)$stmt->fetchColumn();


/*
| Draft
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)

    FROM mark_submissions

    WHERE teacher_id = ?

    AND status = 'Draft'
");

$stmt->execute([
    $teacherId
]);

$draftCount =
    (int)$stmt->fetchColumn();


/*
| Submitted
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)

    FROM mark_submissions

    WHERE teacher_id = ?

    AND status = 'Submitted'
");

$stmt->execute([
    $teacherId
]);

$submittedCount =
    (int)$stmt->fetchColumn();


/*
| Returned
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)

    FROM mark_submissions

    WHERE teacher_id = ?

    AND status = 'Returned'
");

$stmt->execute([
    $teacherId
]);

$returnedCount =
    (int)$stmt->fetchColumn();


/*
| Approved
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)

    FROM mark_submissions

    WHERE teacher_id = ?

    AND status = 'Approved'
");

$stmt->execute([
    $teacherId
]);

$approvedCount =
    (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TEACHING ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        tc.class_id,

        c.class_name

    FROM teacher_classes tc

    INNER JOIN classes c
        ON c.id = tc.class_id

    WHERE tc.teacher_id = ?

    ORDER BY c.class_name ASC
");

$stmt->execute([
    $teacherId
]);

$classes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


$stmt = $conn->prepare("
    SELECT

        ts.subject_id,

        s.subject_name

    FROM teacher_subjects ts

    INNER JOIN subjects s
        ON s.id = ts.subject_id

    WHERE ts.teacher_id = ?

    ORDER BY s.subject_name ASC
");

$stmt->execute([
    $teacherId
]);

$subjects =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| CURRENT ACADEMIC YEAR
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT *

    FROM academic_years

    ORDER BY id DESC

    LIMIT 1
");

$currentYear =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


$currentYearId =
    $currentYear
        ? (int)$currentYear["id"]
        : 0;


$currentYearName =
    $currentYear
        ? $currentYear["academic_year"]
        : "Not Set";


/*
|--------------------------------------------------------------------------
| CURRENT TERM
|--------------------------------------------------------------------------
*/

$currentTerm = null;


if ($currentYearId) {

    $stmt = $conn->prepare("
        SELECT *

        FROM terms

        WHERE academic_year_id = ?

        ORDER BY id DESC

        LIMIT 1
    ");

    $stmt->execute([
        $currentYearId
    ]);

    $currentTerm =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );
}


$currentTermId =
    $currentTerm
        ? (int)$currentTerm["id"]
        : 0;


$currentTermName =
    $currentTerm
        ? $currentTerm["term_name"]
        : "Not Set";


/*
|--------------------------------------------------------------------------
| ASSIGNMENT STATUS
|--------------------------------------------------------------------------
|
| We create a row for each teacher class/subject
| combination.
|
*/

$assignmentRows = [];


if (
    count($classes) > 0 &&
    count($subjects) > 0
) {


    foreach (
        $classes
        as $class
    ) {

        foreach (
            $subjects
            as $subject
        ) {


            /*
            |--------------------------------------------------------------------------
            | STUDENT COUNT
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT COUNT(*)

                FROM students

                WHERE class_id = ?
            ");

            $stmt->execute([
                $class["class_id"]
            ]);

            $studentCount =
                (int)$stmt->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | SUBMISSION
            |--------------------------------------------------------------------------
            */

            $submission = null;


            if (
                $currentTermId
            ) {

                $stmt = $conn->prepare("
                    SELECT

                        id,

                        status,

                        submitted_at,

                        reviewed_at,

                        review_comment

                    FROM mark_submissions

                    WHERE teacher_id = ?

                    AND academic_year_id = ?

                    AND term_id = ?

                    AND class_id = ?

                    AND subject_id = ?

                    LIMIT 1
                ");

                $stmt->execute([

                    $teacherId,

                    $currentYearId,

                    $currentTermId,

                    $class["class_id"],

                    $subject["subject_id"]

                ]);

                $submission =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | MARK COUNT
            |--------------------------------------------------------------------------
            */

            $markCount = 0;


            if (
                $currentTermId
            ) {

                $stmt = $conn->prepare("
                    SELECT COUNT(*)

                    FROM marks m

                    INNER JOIN students s
                        ON s.id = m.student_id

                    WHERE

                        s.class_id = ?

                        AND m.subject_id = ?

                        AND m.term_id = ?
                ");

                $stmt->execute([

                    $class["class_id"],

                    $subject["subject_id"],

                    $currentTermId

                ]);

                $markCount =
                    (int)$stmt->fetchColumn();
            }


            /*
            |--------------------------------------------------------------------------
            | PROGRESS
            |--------------------------------------------------------------------------
            */

            $progress = 0;


            if (
                $studentCount > 0
            ) {

                $progress =
                    min(
                        100,
                        round(
                            (
                                $markCount /
                                $studentCount
                            )
                            * 100
                        )
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $status =
                $submission
                    ? $submission["status"]
                    : "Not Started";


            $assignmentRows[] = [

                "class_id" =>
                    $class["class_id"],

                "class_name" =>
                    $class["class_name"],

                "subject_id" =>
                    $subject["subject_id"],

                "subject_name" =>
                    $subject["subject_name"],

                "student_count" =>
                    $studentCount,

                "mark_count" =>
                    $markCount,

                "progress" =>
                    $progress,

                "status" =>
                    $status,

                "submission_id" =>
                    $submission
                        ? $submission["id"]
                        : null,

                "review_comment" =>
                    $submission
                        ? $submission[
                            "review_comment"
                        ]
                        : null

            ];
        }
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
    HIBS | Teacher Dashboard
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    background: #f5f4f0;

    color: #263238;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: 235px;

    height: 100vh;

    padding:
        27px 17px;

    background: #263238;

    color: #ffffff;

}


.brand {

    padding:
        3px 10px 25px;

    margin-bottom: 20px;

    border-bottom:
        1px solid
        rgba(255,255,255,.12);

}


.brand-title {

    font-size: 17px;

    font-weight: 700;

    letter-spacing: 1px;

}


.brand-subtitle {

    margin-top: 6px;

    color: #aeb8bc;

    font-size: 8px;

    line-height: 1.6;

    letter-spacing: .7px;

}


.teacher-mini {

    padding:
        0 8px 20px;

}


.teacher-name {

    color: #ffffff;

    font-size: 9px;

    font-weight: 600;

}


.teacher-id {

    margin-top: 4px;

    color: #9ba7ab;

    font-size: 6px;

}


.nav-title {

    padding:
        0 10px 7px;

    color: #879399;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: 1px;

}


.nav-link {

    display: block;

    padding: 11px;

    margin-bottom: 4px;

    color: #dce2e5;

    text-decoration: none;

    font-size: 10px;

    border-radius: 4px;

}


.nav-link:hover {

    background: #37474f;

}


.nav-link.active {

    background: #546e7a;

}


.logout {

    position: absolute;

    left: 17px;
    right: 17px;
    bottom: 20px;

    padding: 10px;

    color: #dce2e5;

    text-decoration: none;

    border:
        1px solid
        rgba(255,255,255,.15);

    text-align: center;

    font-size: 9px;

    border-radius: 4px;

}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 235px;

    min-height: 100vh;

}


.topbar {

    height: 70px;

    padding:
        0 32px;

    background: #ffffff;

    border-bottom:
        1px solid
        #deddd8;

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.topbar-title {

    font-size: 16px;

    font-weight: 600;

}


.topbar-right {

    color: #7b878b;

    font-size: 8px;

}


/* =========================================================
   CONTENT
========================================================= */

.content {

    max-width: 1450px;

    padding:
        28px 32px;

}


/* =========================================================
   PAGE TITLE
========================================================= */

.page-title {

    margin-bottom: 22px;

}


.page-title h1 {

    margin: 0;

    font-size: 24px;

    font-weight: 600;

}


.page-title p {

    margin:
        7px 0 0;

    color: #7d898d;

    font-size: 9px;

}


/* =========================================================
   ACADEMIC BAR
========================================================= */

.academic-bar {

    margin-bottom: 20px;

    padding:
        15px 18px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.academic-label {

    color: #7d898d;

    font-size: 7px;

    text-transform: uppercase;

    font-weight: bold;

}


.academic-value {

    margin-top: 4px;

    font-size: 11px;

    font-weight: 600;

}


.academic-right {

    text-align: right;

}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(6, 1fr);

    gap: 10px;

    margin-bottom: 22px;

}


.stat {

    min-height: 100px;

    padding: 16px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.stat-label {

    color: #7b878b;

    font-size: 6px;

    font-weight: bold;

    text-transform: uppercase;

}


.stat-value {

    margin-top: 9px;

    color: #37474f;

    font-size: 22px;

    font-weight: 600;

}


.stat-note {

    margin-top: 4px;

    color: #9aa3a6;

    font-size: 6px;

}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-actions {

    display: flex;

    gap: 8px;

    margin-bottom: 22px;

}


.quick {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 150px;

    height: 38px;

    padding:
        0 15px;

    border-radius: 3px;

    text-decoration: none;

    font-size: 8px;

    font-weight: bold;

}


.quick-primary {

    background: #455a64;

    color: #ffffff;

}


.quick-secondary {

    background: #ffffff;

    border:
        1px solid
        #d2d1cc;

    color: #5f6d72;

}


/* =========================================================
   PANEL
========================================================= */

.panel {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.panel-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid
        #e7e5e1;

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.panel-title {

    font-size: 14px;

    font-weight: 600;

}


.panel-subtitle {

    margin-top: 5px;

    color: #899398;

    font-size: 7px;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrap {

    overflow-x: auto;

}


table {

    width: 100%;

    min-width: 1000px;

    border-collapse: collapse;

}


th {

    padding:
        11px 9px;

    background: #f1f2ef;

    border-bottom:
        1px solid
        #d8d7d2;

    color: #68767b;

    font-size: 7px;

    text-align: left;

    text-transform: uppercase;

}


td {

    padding:
        11px 9px;

    border-bottom:
        1px solid
        #eceae6;

    color: #455a64;

    font-size: 8px;

    vertical-align: middle;

}


.class-name {

    font-weight: 600;

}


.subject-name {

    color: #6f7c80;

}


.progress-wrap {

    width: 100px;

}


.progress {

    height: 5px;

    background: #e7e8e5;

    overflow: hidden;

    border-radius: 5px;

}


.progress-bar {

    height: 100%;

    background: #607d8b;

}


.progress-text {

    margin-top: 4px;

    color: #899398;

    font-size: 6px;

}


.status {

    display: inline-block;

    padding:
        6px 8px;

    font-size: 6px;

    font-weight: bold;

    text-transform: uppercase;

}


.status-not {

    background: #eeeeec;

    color: #747f83;

}


.status-draft {

    background: #edf0f1;

    color: #586b72;

}


.status-submitted {

    background: #e7edf0;

    color: #4f6873;

}


.status-returned {

    background: #f6eee5;

    color: #846343;

}


.status-approved {

    background: #e7f1ea;

    color: #416c4e;

}


.action {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 80px;

    height: 30px;

    padding:
        0 9px;

    border-radius: 3px;

    text-decoration: none;

    font-size: 7px;

    font-weight: bold;

}


.action-primary {

    background: #455a64;

    color: #ffffff;

}


.action-light {

    background: #ffffff;

    border:
        1px solid
        #d3d2ce;

    color: #5d6b70;

}


.review-note {

    max-width: 180px;

    margin-top: 5px;

    color: #896b49;

    font-size: 6px;

    line-height: 1.5;

}


.empty {

    padding:
        55px 20px;

    text-align: center;

    color: #899398;

    font-size: 9px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .stats {

        grid-template-columns:
            repeat(3, 1fr);

    }

}


@media(max-width:700px) {

    .sidebar {

        position: static;

        width: 100%;

        height: auto;

        padding: 15px;

    }


    .nav-title {

        display: none;

    }


    .nav-link {

        display: inline-block;

        padding: 8px;

    }


    .logout {

        position: static;

        margin-top: 12px;

    }


    .main {

        margin-left: 0;

    }


    .content {

        padding:
            20px 15px;

    }


    .stats {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .academic-bar {

        align-items: flex-start;

        flex-direction: column;

        gap: 12px;

    }


    .academic-right {

        text-align: left;

    }


    .quick-actions {

        flex-direction: column;

    }


    .quick {

        width: 100%;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar">


<div class="brand">

    <div class="brand-title">
        HIBS REPORTS
    </div>

    <div class="brand-subtitle">

        HILLTOP INTERNATIONAL<br>
        BRITISH SCHOOL

    </div>

</div>


<div class="teacher-mini">

    <div class="teacher-name">

        <?= h(
            $teacherName
        ) ?>

    </div>

    <div class="teacher-id">

        Employee ID:

        <?= h(
            $teacher[
                "employee_id"
            ]
            ??
            "Not Assigned"
        ) ?>

    </div>

</div>


<div class="nav-title">
    Teacher Portal
</div>


<a
    href="dashboard.php"
    class="nav-link active"
>
    Dashboard
</a>


<a
    href="marks.php"
    class="nav-link"
>
    Marks Entry
</a>


<a
    href="students.php"
    class="nav-link"
>
    My Students
</a>


<a
    href="reports.php"
    class="nav-link"
>
    My Reports
</a>


<a
    href="profile.php"
    class="nav-link"
>
    My Profile
</a>


<a
    href="../logout.php"
    class="logout"
>
    Sign Out
</a>


</aside>


<!-- =====================================================
     MAIN
====================================================== -->

<div class="main">


<header class="topbar">


<div class="topbar-title">

    Teacher Dashboard

</div>


<div class="topbar-right">

    <?= h(
        $teacher[
            "email"
        ]
    ) ?>

</div>


</header>


<main class="content">


<!-- =====================================================
     TITLE
====================================================== -->

<div class="page-title">

    <h1>

        Welcome,
        <?= h(
            $teacherName
        ) ?>

    </h1>

    <p>

        Manage your assigned classes, subjects and
        academic marks.

    </p>

</div>


<!-- =====================================================
     ACADEMIC YEAR
====================================================== -->

<div class="academic-bar">


<div>

    <div class="academic-label">
        Academic Year
    </div>

    <div class="academic-value">

        <?= h(
            $currentYearName
        ) ?>

    </div>

</div>


<div class="academic-right">

    <div class="academic-label">
        Current Term
    </div>

    <div class="academic-value">

        <?= h(
            $currentTermName
        ) ?>

    </div>

</div>


</div>


<!-- =====================================================
     STATISTICS
====================================================== -->

<div class="stats">


<div class="stat">

    <div class="stat-label">
        Assigned Classes
    </div>

    <div class="stat-value">

        <?= $totalClasses ?>

    </div>

    <div class="stat-note">
        Classes assigned to you
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Subjects
    </div>

    <div class="stat-value">

        <?= $totalSubjects ?>

    </div>

    <div class="stat-note">
        Subjects you teach
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Students
    </div>

    <div class="stat-value">

        <?= $totalStudents ?>

    </div>

    <div class="stat-note">
        Students in assigned classes
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Draft
    </div>

    <div class="stat-value">

        <?= $draftCount ?>

    </div>

    <div class="stat-note">
        Mark submissions in progress
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Awaiting Review
    </div>

    <div class="stat-value">

        <?= $submittedCount ?>

    </div>

    <div class="stat-note">
        Submitted to administration
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Approved
    </div>

    <div class="stat-value">

        <?= $approvedCount ?>

    </div>

    <div class="stat-note">
        Approved submissions
    </div>

</div>


</div>


<!-- =====================================================
     QUICK ACTIONS
====================================================== -->

<div class="quick-actions">


<a
    href="marks.php"
    class="quick quick-primary"
>
    Enter Student Marks
</a>


<a
    href="students.php"
    class="quick quick-secondary"
>
    View My Students
</a>


<a
    href="reports.php"
    class="quick quick-secondary"
>
    View My Reports
</a>


</div>


<!-- =====================================================
     RETURNED NOTICE
====================================================== -->

<?php if (
    $returnedCount > 0
): ?>


<div
    style="
        margin-bottom:20px;
        padding:15px;
        background:#f7eee5;
        border:1px solid #e3d1bd;
        color:#765a3d;
        font-size:8px;
    "
>

    <strong>
        <?= $returnedCount ?>
    </strong>

    mark submission(s) have been returned
    by administration for correction.

    Please review the submission register below.

</div>


<?php endif; ?>


<!-- =====================================================
     SUBMISSION REGISTER
====================================================== -->

<section class="panel">


<div class="panel-header">


<div>

    <div class="panel-title">

        Current Teaching & Mark Status

    </div>

    <div class="panel-subtitle">

        Academic year:
        <?= h(
            $currentYearName
        ) ?>

        &nbsp; • &nbsp;

        Term:
        <?= h(
            $currentTermName
        ) ?>

    </div>

</div>


</div>


<?php if (
    count($assignmentRows) > 0
): ?>


<div class="table-wrap">


<table>


<thead>

<tr>

    <th>
        Class
    </th>

    <th>
        Subject
    </th>

    <th>
        Students
    </th>

    <th>
        Marks Progress
    </th>

    <th>
        Status
    </th>

    <th>
        Action
    </th>

</tr>

</thead>


<tbody>


<?php foreach (
    $assignmentRows
    as $row
): ?>


<tr>


<td>

    <div class="class-name">

        <?= h(
            $row[
                "class_name"
            ]
        ) ?>

    </div>

</td>


<td>

    <div class="subject-name">

        <?= h(
            $row[
                "subject_name"
            ]
        ) ?>

    </div>

</td>


<td>

    <?= h(
        $row[
            "mark_count"
        ]
    ) ?>

    /

    <?= h(
        $row[
            "student_count"
        ]
    ) ?>

</td>


<td>


<div class="progress-wrap">


<div class="progress">

    <div
        class="progress-bar"
        style="
            width:
            <?= (int)$row["progress"] ?>%;
        "
    ></div>

</div>


<div class="progress-text">

    <?= (int)$row[
        "progress"
    ] ?>%

    completed

</div>


</div>


</td>


<td>


<?php

$statusClass =
    "status-not";


switch (
    $row["status"]
) {

    case "Draft":

        $statusClass =
            "status-draft";

        break;


    case "Submitted":

        $statusClass =
            "status-submitted";

        break;


    case "Returned":

        $statusClass =
            "status-returned";

        break;


    case "Approved":

        $statusClass =
            "status-approved";

        break;

}

?>


<span
    class="
        status
        <?= $statusClass ?>
    "
>

    <?= h(
        $row[
            "status"
        ]
    ) ?>

</span>


<?php if (
    $row[
        "status"
    ]
    ===
    "Returned"
    &&
    !empty(
        $row[
            "review_comment"
        ]
    )
): ?>


<div class="review-note">

    <?= h(
        $row[
            "review_comment"
        ]
    ) ?>

</div>


<?php endif; ?>


</td>


<td>


<?php if (
    $row[
        "status"
    ]
    ===
    "Submitted"
    ||
    $row[
        "status"
    ]
    ===
    "Approved"
): ?>


<span
    class="action action-light"
    style="
        cursor:default;
    "
>

    Locked

</span>


<?php elseif (
    $row[
        "status"
    ]
    ===
    "Returned"
): ?>


<a
    href="marks.php?academic_year_id=<?= (int)$currentYearId ?>&term_id=<?= (int)$currentTermId ?>&class_id=<?= (int)$row["class_id"] ?>&subject_id=<?= (int)$row["subject_id"] ?>"
    class="action action-primary"
>

    Correct Marks

</a>


<?php else: ?>


<a
    href="marks.php?academic_year_id=<?= (int)$currentYearId ?>&term_id=<?= (int)$currentTermId ?>&class_id=<?= (int)$row["class_id"] ?>&subject_id=<?= (int)$row["subject_id"] ?>"
    class="action action-primary"
>

    Enter Marks

</a>


<?php endif; ?>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty">

    No teaching assignments have been made to your
    teacher account yet.

</div>


<?php endif; ?>


</section>


</main>


</div>


</body>

</html>
