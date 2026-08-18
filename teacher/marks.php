<?php

session_start();

require_once "../config/db.php";
require_once "../config/grading.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| TEACHER MARKS ENTRY
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
| FIND TEACHER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        employee_id,
        phone,
        qualification,
        specialization
    FROM teachers
    WHERE user_id = ?
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


/*
|--------------------------------------------------------------------------
| SELECTED VALUES
|--------------------------------------------------------------------------
*/

$academicYearId =
    filter_input(
        INPUT_GET,
        "academic_year_id",
        FILTER_VALIDATE_INT
    );

$termId =
    filter_input(
        INPUT_GET,
        "term_id",
        FILTER_VALIDATE_INT
    );

$classId =
    filter_input(
        INPUT_GET,
        "class_id",
        FILTER_VALIDATE_INT
    );

$subjectId =
    filter_input(
        INPUT_GET,
        "subject_id",
        FILTER_VALIDATE_INT
    );


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$error = "";

$success = "";


/*
|--------------------------------------------------------------------------
| SAVE MARKS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $postAcademicYear =
        filter_input(
            INPUT_POST,
            "academic_year_id",
            FILTER_VALIDATE_INT
        );

    $postTerm =
        filter_input(
            INPUT_POST,
            "term_id",
            FILTER_VALIDATE_INT
        );

    $postClass =
        filter_input(
            INPUT_POST,
            "class_id",
            FILTER_VALIDATE_INT
        );

    $postSubject =
        filter_input(
            INPUT_POST,
            "subject_id",
            FILTER_VALIDATE_INT
        );


    /*
    |--------------------------------------------------------------------------
    | VERIFY TEACHER CLASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    $checkClass =
        $conn->prepare("
            SELECT COUNT(*)

            FROM teacher_classes

            WHERE
                teacher_id = ?
                AND class_id = ?
        ");

    $checkClass->execute([

        $teacherId,

        $postClass

    ]);


    $classAssigned =
        (int)$checkClass->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | VERIFY TEACHER SUBJECT ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    $checkSubject =
        $conn->prepare("
            SELECT COUNT(*)

            FROM teacher_subjects

            WHERE
                teacher_id = ?
                AND subject_id = ?
        ");

    $checkSubject->execute([

        $teacherId,

        $postSubject

    ]);


    $subjectAssigned =
        (int)$checkSubject->fetchColumn();


    if (
        !$classAssigned ||
        !$subjectAssigned
    ) {

        $error =
            "You are not assigned to this class and subject.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | VALIDATE TERM
        |--------------------------------------------------------------------------
        */

        $checkTerm =
            $conn->prepare("
                SELECT COUNT(*)

                FROM terms

                WHERE
                    id = ?
                    AND academic_year_id = ?
            ");

        $checkTerm->execute([

            $postTerm,

            $postAcademicYear

        ]);


        if (
            !(int)$checkTerm->fetchColumn()
        ) {

            $error =
                "The selected term does not belong to the selected academic year.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | PROCESS MARKS
            |--------------------------------------------------------------------------
            */

            $marks =
                $_POST["marks"]
                ?? [];


            try {

                $conn->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | CHECK WHETHER REPORT IS PUBLISHED
                |--------------------------------------------------------------------------
                */

                $reportCheck =
                    $conn->prepare("
                        SELECT
                            id,
                            report_status

                        FROM report_card_records

                        WHERE

                            class_id = ?

                            AND term_id = ?

                            AND report_status = 'Published'

                        LIMIT 1
                    ");

                $reportCheck->execute([

                    $postClass,

                    $postTerm

                ]);


                $publishedReport =
                    $reportCheck->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    $publishedReport
                ) {

                    throw new Exception(
                        "This term has already been published. Marks are locked."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | SAVE EACH STUDENT
                |--------------------------------------------------------------------------
                */

                $save =
                    $conn->prepare("
                        INSERT INTO marks (

                            student_id,
                            subject_id,
                            term_id,
                            classwork,
                            test,
                            examination,
                            total,
                            grade,
                            grade_description

                        )

                        VALUES (

                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?

                        )

                        ON DUPLICATE KEY UPDATE

                            classwork =
                                VALUES(classwork),

                            test =
                                VALUES(test),

                            examination =
                                VALUES(examination),

                            total =
                                VALUES(total),

                            grade =
                                VALUES(grade),

                            grade_description =
                                VALUES(grade_description)
                    ");


                foreach (
                    $marks
                    as $studentId =>
                    $values
                ) {


                    $studentId =
                        (int)$studentId;


                    /*
                    |--------------------------------------------------------------------------
                    | GET VALUES
                    |--------------------------------------------------------------------------
                    */

                    $classwork =
                        isset(
                            $values["classwork"]
                        )
                        &&
                        $values["classwork"] !== ""
                            ? (float)$values["classwork"]
                            : 0;


                    $test =
                        isset(
                            $values["test"]
                        )
                        &&
                        $values["test"] !== ""
                            ? (float)$values["test"]
                            : 0;


                    $examination =
                        isset(
                            $values["examination"]
                        )
                        &&
                        $values["examination"] !== ""
                            ? (float)$values["examination"]
                            : 0;


                    /*
                    |--------------------------------------------------------------------------
                    | VALIDATE
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $classwork < 0 ||
                        $test < 0 ||
                        $examination < 0
                    ) {

                        throw new Exception(
                            "Marks cannot be negative."
                        );
                    }


                    $total =
                        $classwork +
                        $test +
                        $examination;


                    if (
                        $total > 100
                    ) {

                        throw new Exception(
                            "The total mark for a student cannot exceed 100."
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | GRADE
                    |--------------------------------------------------------------------------
                    */

                    $grade =
                        hibs_get_grade(
                            $total
                        );


                    $description =
                        hibs_grade_description(
                            $grade
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | VERIFY STUDENT BELONGS TO CLASS
                    |--------------------------------------------------------------------------
                    */

                    $studentCheck =
                        $conn->prepare("
                            SELECT COUNT(*)

                            FROM students

                            WHERE
                                id = ?
                                AND class_id = ?
                        ");

                    $studentCheck->execute([

                        $studentId,

                        $postClass

                    ]);


                    if (
                        !(int)$studentCheck->fetchColumn()
                    ) {

                        throw new Exception(
                            "A selected student does not belong to this class."
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SAVE
                    |--------------------------------------------------------------------------
                    */

                    $save->execute([

                        $studentId,

                        $postSubject,

                        $postTerm,

                        $classwork,

                        $test,

                        $examination,

                        $total,

                        $grade,

                        $description

                    ]);
                }


                $conn->commit();


                $success =
                    "Marks saved successfully.";


                /*
                |--------------------------------------------------------------------------
                | PRESERVE FILTERS
                |--------------------------------------------------------------------------
                */

                $academicYearId =
                    $postAcademicYear;

                $termId =
                    $postTerm;

                $classId =
                    $postClass;

                $subjectId =
                    $postSubject;


            } catch (
                Throwable $e
            ) {


                if (
                    $conn->inTransaction()
                ) {

                    $conn->rollBack();
                }


                $error =
                    $e->getMessage();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ACADEMIC YEARS
|--------------------------------------------------------------------------
*/

$academicYears =
    $conn->query("
        SELECT
            id,
            academic_year

        FROM academic_years

        ORDER BY id DESC
    ")->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| TERMS
|--------------------------------------------------------------------------
*/

$terms = [];


if (
    $academicYearId
) {

    $stmt =
        $conn->prepare("
            SELECT
                id,
                term_name

            FROM terms

            WHERE academic_year_id = ?

            ORDER BY id ASC
        ");

    $stmt->execute([
        $academicYearId
    ]);

    $terms =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


/*
|--------------------------------------------------------------------------
| ASSIGNED CLASSES
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT

            c.id,
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


/*
|--------------------------------------------------------------------------
| ASSIGNED SUBJECTS
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT

            s.id,
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
| STUDENTS
|--------------------------------------------------------------------------
*/

$students = [];

$currentMarks = [];


if (
    $classId &&
    $subjectId &&
    $termId
) {


    /*
    |--------------------------------------------------------------------------
    | CHECK ASSIGNMENTS AGAIN
    |--------------------------------------------------------------------------
    */

    $validClass = false;

    $validSubject = false;


    foreach (
        $classes
        as $class
    ) {

        if (
            (int)$class["id"]
            ===
            (int)$classId
        ) {

            $validClass = true;

            break;
        }
    }


    foreach (
        $subjects
        as $subject
    ) {

        if (
            (int)$subject["id"]
            ===
            (int)$subjectId
        ) {

            $validSubject = true;

            break;
        }
    }


    if (
        $validClass &&
        $validSubject
    ) {


        /*
        |--------------------------------------------------------------------------
        | STUDENTS
        |--------------------------------------------------------------------------
        */

        $stmt =
            $conn->prepare("
                SELECT

                    id,
                    student_id,
                    first_name,
                    middle_name,
                    last_name

                FROM students

                WHERE class_id = ?

                ORDER BY
                    first_name ASC,
                    last_name ASC
            ");

        $stmt->execute([
            $classId
        ]);

        $students =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | EXISTING MARKS
        |--------------------------------------------------------------------------
        */

        if (
            count($students)
        ) {

            $studentIds =
                array_column(
                    $students,
                    "id"
                );


            $placeholders =
                implode(
                    ",",
                    array_fill(
                        0,
                        count($studentIds),
                        "?"
                    )
                );


            $params =
                array_merge(

                    $studentIds,

                    [
                        $subjectId,
                        $termId
                    ]

                );


            $stmt =
                $conn->prepare("
                    SELECT

                        student_id,
                        classwork,
                        test,
                        examination,
                        total,
                        grade,
                        grade_description

                    FROM marks

                    WHERE

                        student_id IN (
                            $placeholders
                        )

                        AND subject_id = ?

                        AND term_id = ?
                ");


            $stmt->execute(
                $params
            );


            while (
                $row =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                )
            ) {

                $currentMarks[
                    $row["student_id"]
                ] =
                    $row;
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| SELECTED NAMES
|--------------------------------------------------------------------------
*/

$selectedClassName = "";

foreach (
    $classes
    as $class
) {

    if (
        (int)$class["id"]
        ===
        (int)$classId
    ) {

        $selectedClassName =
            $class["class_name"];

        break;
    }
}


$selectedSubjectName = "";

foreach (
    $subjects
    as $subject
) {

    if (
        (int)$subject["id"]
        ===
        (int)$subjectId
    ) {

        $selectedSubjectName =
            $subject["subject_name"];

        break;
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
    HIBS Reports | Marks Entry
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

    left: 0;
    top: 0;

    width: 235px;

    height: 100vh;

    background: #263238;

    padding: 27px 17px;

    color: #ffffff;

}


.brand {

    padding:
        3px 10px 25px;

    border-bottom:
        1px solid
        rgba(255,255,255,.12);

    margin-bottom: 20px;

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

    letter-spacing: .8px;

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

    display: block;

    padding: 10px;

    border:
        1px solid
        rgba(255,255,255,.15);

    color: #dce2e5;

    text-decoration: none;

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


.teacher-name {

    color: #7b878b;

    font-size: 9px;

}


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding:
        28px 32px;

    max-width: 1450px;

}


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
   ALERTS
========================================================= */

.alert {

    margin-bottom: 18px;

    padding: 13px 15px;

    font-size: 9px;

    border: 1px solid;

}


.alert-success {

    background: #ebf3ed;

    color: #426b50;

    border-color: #cadccd;

}


.alert-error {

    background: #fbefef;

    color: #8b4b4b;

    border-color: #e1c9c9;

}


/* =========================================================
   FILTER PANEL
========================================================= */

.panel {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    margin-bottom: 20px;

}


.panel-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid
        #e7e5e1;

}


.panel-header h2 {

    margin: 0;

    font-size: 14px;

    font-weight: 600;

}


.panel-header p {

    margin:
        5px 0 0;

    color: #8a9498;

    font-size: 8px;

}


.filters {

    padding: 20px;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;

}


.field label {

    display: block;

    margin-bottom: 6px;

    color: #69767b;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

}


.field select {

    width: 100%;

    height: 38px;

    padding:
        0 10px;

    border:
        1px solid
        #d2d1cc;

    background: #ffffff;

    color: #455a64;

    font-family: inherit;

    font-size: 9px;

    border-radius: 3px;

}


.filter-action {

    padding:
        0 20px 20px;

}


.btn {

    display: inline-block;

    padding:
        10px 15px;

    border: 0;

    border-radius: 3px;

    font-family: inherit;

    font-size: 8px;

    font-weight: bold;

    text-decoration: none;

    cursor: pointer;

}


.btn-primary {

    background: #455a64;

    color: #ffffff;

}


.btn-primary:hover {

    background: #263238;

}


/* =========================================================
   MARKS TABLE
========================================================= */

.marks-panel {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.marks-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid
        #e7e5e1;

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.marks-header h2 {

    margin: 0;

    font-size: 14px;

    font-weight: 600;

}


.marks-header p {

    margin:
        5px 0 0;

    color: #8a9498;

    font-size: 8px;

}


.save-button {

    padding:
        10px 15px;

    border: 0;

    border-radius: 3px;

    background: #455a64;

    color: #ffffff;

    font-family: inherit;

    font-size: 8px;

    font-weight: bold;

    cursor: pointer;

}


.table-wrap {

    overflow-x: auto;

}


table {

    width: 100%;

    min-width: 900px;

    border-collapse: collapse;

}


thead th {

    padding:
        11px 8px;

    background: #f1f2ef;

    border-bottom:
        1px solid
        #d8d7d2;

    color: #68767b;

    font-size: 7px;

    text-align: left;

    text-transform: uppercase;

}


tbody td {

    padding:
        8px;

    border-bottom:
        1px solid
        #eceae6;

    color: #455a64;

    font-size: 9px;

}


.student-number {

    width: 7%;

    color: #899398;

}


.student-name {

    min-width: 230px;

    font-weight: 600;

}


.mark-input {

    width: 85px;

    height: 34px;

    padding:
        0 8px;

    border:
        1px solid
        #d1d0cb;

    border-radius: 3px;

    background: #ffffff;

    color: #37474f;

    font-family: inherit;

    font-size: 9px;

    text-align: center;

}


.mark-input:focus {

    outline: none;

    border-color: #607d8b;

}


.total {

    font-weight: 700;

    text-align: center;

}


.grade {

    text-align: center;

    font-weight: 700;

}


.grade-a {

    color: #3f6d50;

}


.grade-b {

    color: #4e7259;

}


.grade-c {

    color: #66785b;

}


.grade-d {

    color: #88744d;

}


.grade-e {

    color: #8a704e;

}


.grade-f,
.grade-u {

    color: #985858;

}


.empty {

    padding:
        60px 20px;

    text-align: center;

    color: #899398;

    font-size: 9px;

}


.summary {

    padding:
        15px 20px;

    background: #fafaf8;

    border-top:
        1px solid
        #e4e3de;

    color: #7b878b;

    font-size: 8px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:950px) {

    .filters {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media(max-width:700px) {

    .sidebar {

        position: static;

        width: 100%;

        height: auto;

        padding: 15px;

    }


    .brand {

        padding-bottom: 15px;

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


    .topbar {

        padding:
            0 18px;

    }


    .content {

        padding:
            20px 15px;

    }


    .filters {

        grid-template-columns: 1fr;

    }


    .marks-header {

        align-items: flex-start;

        flex-direction: column;

        gap: 12px;

    }


    .save-button {

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


    <div class="nav-title">
        Teacher Portal
    </div>


    <a
        href="dashboard.php"
        class="nav-link"
    >
        Dashboard
    </a>


    <a
        href="marks.php"
        class="nav-link active"
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

        Marks Entry

    </div>


    <div class="teacher-name">

        Teacher Portal

    </div>


</header>


<main class="content">


    <div class="page-title">

        <h1>
            Student Marks Entry
        </h1>

        <p>
            Enter continuous assessment and examination
            marks for students assigned to you.
        </p>

    </div>


    <?php if (
        $success !== ""
    ): ?>

        <div class="alert alert-success">

            <?= h(
                $success
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (
        $error !== ""
    ): ?>

        <div class="alert alert-error">

            <?= h(
                $error
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         SELECTION
    ================================================== -->

    <section class="panel">


        <div class="panel-header">

            <h2>
                Select Teaching Context
            </h2>

            <p>
                Select the academic year, term, class and
                subject before entering marks.
            </p>

        </div>


        <form
            method="GET"
        >


            <div class="filters">


                <!-- YEAR -->

                <div class="field">

                    <label>
                        Academic Year
                    </label>

                    <select
                        name="academic_year_id"
                        onchange="this.form.submit()"
                        required
                    >

                        <option value="">
                            Select Academic Year
                        </option>


                        <?php foreach (
                            $academicYears
                            as $year
                        ): ?>

                            <option
                                value="<?= (int)$year["id"] ?>"
                                <?= (
                                    (int)$academicYearId
                                    ===
                                    (int)$year["id"]
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= h(
                                    $year[
                                        "academic_year"
                                    ]
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- TERM -->

                <div class="field">

                    <label>
                        Term
                    </label>

                    <select
                        name="term_id"
                        required
                    >

                        <option value="">
                            Select Term
                        </option>


                        <?php foreach (
                            $terms
                            as $term
                        ): ?>

                            <option
                                value="<?= (int)$term["id"] ?>"
                                <?= (
                                    (int)$termId
                                    ===
                                    (int)$term["id"]
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= h(
                                    $term[
                                        "term_name"
                                    ]
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- CLASS -->

                <div class="field">

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
                                <?= (
                                    (int)$classId
                                    ===
                                    (int)$class["id"]
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= h(
                                    $class[
                                        "class_name"
                                    ]
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- SUBJECT -->

                <div class="field">

                    <label>
                        Subject
                    </label>

                    <select
                        name="subject_id"
                        required
                    >

                        <option value="">
                            Select Subject
                        </option>


                        <?php foreach (
                            $subjects
                            as $subject
                        ): ?>

                            <option
                                value="<?= (int)$subject["id"] ?>"
                                <?= (
                                    (int)$subjectId
                                    ===
                                    (int)$subject["id"]
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= h(
                                    $subject[
                                        "subject_name"
                                    ]
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


            </div>


            <div class="filter-action">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Load Students
                </button>

            </div>


        </form>


    </section>


    <!-- =================================================
         MARKS
    ================================================== -->

    <?php if (
        count($students) > 0
    ): ?>


        <form
            method="POST"
            class="marks-panel"
        >


            <input
                type="hidden"
                name="academic_year_id"
                value="<?= (int)$academicYearId ?>"
            >


            <input
                type="hidden"
                name="term_id"
                value="<?= (int)$termId ?>"
            >


            <input
                type="hidden"
                name="class_id"
                value="<?= (int)$classId ?>"
            >


            <input
                type="hidden"
                name="subject_id"
                value="<?= (int)$subjectId ?>"
            >


            <div class="marks-header">


                <div>

                    <h2>

                        <?= h(
                            $selectedClassName
                        ) ?>

                        —
                        <?= h(
                            $selectedSubjectName
                        ) ?>

                    </h2>


                    <p>

                        <?= count(
                            $students
                        ) ?>

                        student(s)

                    </p>

                </div>


                <button
                    type="submit"
                    class="save-button"
                >

                    Save All Marks

                </button>


            </div>


            <div class="table-wrap">


                <table>


                    <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Student
                        </th>

                        <th>
                            Classwork
                        </th>

                        <th>
                            Test
                        </th>

                        <th>
                            Examination
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Grade
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


                        <?php

                        $sid =
                            (int)$student["id"];


                        $existing =
                            $currentMarks[
                                $sid
                            ]
                            ?? [];


                        $classwork =
                            $existing[
                                "classwork"
                            ]
                            ?? "";


                        $test =
                            $existing[
                                "test"
                            ]
                            ?? "";


                        $examination =
                            $existing[
                                "examination"
                            ]
                            ?? "";


                        $total =
                            $existing[
                                "total"
                            ]
                            ?? "";


                        $grade =
                            $existing[
                                "grade"
                            ]
                            ?? "";

                        ?>


                        <tr>


                            <td class="student-number">

                                <?= $counter++ ?>

                            </td>


                            <td class="student-name">

                                <?= h(
                                    trim(
                                        implode(
                                            " ",
                                            array_filter([
                                                $student[
                                                    "first_name"
                                                ] ?? "",

                                                $student[
                                                    "middle_name"
                                                ] ?? "",

                                                $student[
                                                    "last_name"
                                                ] ?? ""
                                            ])
                                        )
                                    )
                                ) ?>


                                <br>


                                <span
                                    style="
                                        color:#98a1a4;
                                        font-size:7px;
                                        font-weight:normal;
                                    "
                                >

                                    <?= h(
                                        $student[
                                            "student_id"
                                        ]
                                    ) ?>

                                </span>


                            </td>


                            <td>

                                <input
                                    type="number"
                                    class="mark-input mark-field"
                                    name="marks[<?= $sid ?>][classwork]"
                                    value="<?= h($classwork) ?>"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    data-student="<?= $sid ?>"
                                >

                            </td>


                            <td>

                                <input
                                    type="number"
                                    class="mark-input mark-field"
                                    name="marks[<?= $sid ?>][test]"
                                    value="<?= h($test) ?>"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    data-student="<?= $sid ?>"
                                >

                            </td>


                            <td>

                                <input
                                    type="number"
                                    class="mark-input mark-field"
                                    name="marks[<?= $sid ?>][examination]"
                                    value="<?= h($examination) ?>"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    data-student="<?= $sid ?>"
                                >

                            </td>


                            <td
                                class="total"
                                id="total-<?= $sid ?>"
                            >

                                <?= h(
                                    $total
                                ) ?>

                            </td>


                            <td
                                class="grade"
                                id="grade-<?= $sid ?>"
                            >

                                <?= h(
                                    $grade
                                ) ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


            <div class="summary">

                Classwork + Test + Examination =
                Total Score out of 100.

                Grades are calculated automatically.

            </div>


        </form>


    <?php elseif (
        $classId &&
        $subjectId &&
        $termId
    ): ?>


        <section class="marks-panel">

            <div class="empty">

                No students were found in this class.

            </div>

        </section>


    <?php endif; ?>


</main>


</div>


<script>

/*
|--------------------------------------------------------------------------
| LIVE TOTAL + GRADE
|--------------------------------------------------------------------------
*/

function calculateRow(
    studentId
) {


    const classwork =
        parseFloat(
            document.querySelector(
                '[name="marks[' +
                studentId +
                '][classwork]"]'
            ).value
        ) || 0;


    const test =
        parseFloat(
            document.querySelector(
                '[name="marks[' +
                studentId +
                '][test]"]'
            ).value
        ) || 0;


    const examination =
        parseFloat(
            document.querySelector(
                '[name="marks[' +
                studentId +
                '][examination]"]'
            ).value
        ) || 0;


    const total =
        classwork +
        test +
        examination;


    const totalElement =
        document.getElementById(
            "total-" +
            studentId
        );


    const gradeElement =
        document.getElementById(
            "grade-" +
            studentId
        );


    if (
        totalElement
    ) {

        totalElement.textContent =
            total.toFixed(2);

    }


    let grade = "";


    if (
        total >= 90
    ) {

        grade = "A*";

    } else if (
        total >= 80
    ) {

        grade = "A";

    } else if (
        total >= 70
    ) {

        grade = "B";

    } else if (
        total >= 60
    ) {

        grade = "C";

    } else if (
        total >= 50
    ) {

        grade = "D";

    } else if (
        total >= 40
    ) {

        grade = "E";

    } else if (
        total >= 30
    ) {

        grade = "F";

    } else {

        grade = "U";

    }


    if (
        gradeElement
    ) {

        gradeElement.textContent =
            grade;

    }

}


/*
|--------------------------------------------------------------------------
| LISTEN TO MARK INPUTS
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        ".mark-field"
    )
    .forEach(
        function(input) {

            input.addEventListener(
                "input",
                function() {

                    calculateRow(
                        this.dataset.student
                    );

                }
            );

        }
    );


/*
|--------------------------------------------------------------------------
| INITIAL CALCULATION
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        ".mark-field"
    )
    .forEach(
        function(input) {

            calculateRow(
                input.dataset.student
            );

        }
    );


/*
|--------------------------------------------------------------------------
| PREVENT TOTAL ABOVE 100
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        ".mark-field"
    )
    .forEach(
        function(input) {

            input.addEventListener(
                "input",
                function() {

                    const studentId =
                        this.dataset.student;


                    const classwork =
                        parseFloat(
                            document.querySelector(
                                '[name="marks[' +
                                studentId +
                                '][classwork]"]'
                            ).value
                        ) || 0;


                    const test =
                        parseFloat(
                            document.querySelector(
                                '[name="marks[' +
                                studentId +
                                '][test]"]'
                            ).value
                        ) || 0;


                    const examination =
                        parseFloat(
                            document.querySelector(
                                '[name="marks[' +
                                studentId +
                                '][examination]"]'
                            ).value
                        ) || 0;


                    if (
                        classwork +
                        test +
                        examination
                        >
                        100
                    ) {

                        this.setCustomValidity(
                            "The three marks cannot total more than 100."
                        );

                    } else {

                        this.setCustomValidity(
                            ""
                        );

                    }

                }
            );

        }
    );

</script>


</body>

</html>
