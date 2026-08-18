<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| TEACHER CLASS & SUBJECT ASSIGNMENTS
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| ERROR HANDLING
|--------------------------------------------------------------------------
*/

ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| ADMIN SECURITY
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION["user_id"]) ||
    strtolower((string)($_SESSION["role"] ?? "")) !== "admin"
) {
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| HELPERS
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


function tableExists(
    PDO $conn,
    string $table
): bool {

    $stmt = $conn->prepare("
        SELECT COUNT(*)

        FROM information_schema.tables

        WHERE table_schema = DATABASE()

        AND table_name = ?
    ");

    $stmt->execute([
        $table
    ]);

    return (int)$stmt->fetchColumn() > 0;
}


function getColumns(
    PDO $conn,
    string $table
): array {

    $stmt = $conn->prepare("
        SELECT column_name

        FROM information_schema.columns

        WHERE table_schema = DATABASE()

        AND table_name = ?

        ORDER BY ordinal_position
    ");

    $stmt->execute([
        $table
    ]);

    return $stmt->fetchAll(
        PDO::FETCH_COLUMN
    );
}


function hasColumn(
    array $columns,
    string $column
): bool {

    return in_array(
        $column,
        $columns,
        true
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE VALIDATION
|--------------------------------------------------------------------------
*/

$requiredTables = [
    "teachers",
    "classes",
    "subjects",
    "teacher_class_subjects"
];


foreach (
    $requiredTables as $table
) {

    if (
        !tableExists(
            $conn,
            $table
        )
    ) {

        die("
            <div style=\"
                font-family:Arial;
                padding:30px;
                color:#8a4b4b;
                background:#fff4f4;
                border:1px solid #e3caca;
            \">
                <h2>HIBS Reports Database Error</h2>

                <p>
                    Required table
                    <strong>" .
                    h($table) .
                    "</strong>
                    does not exist.
                </p>

                <p>
                    Please run the HIBS database setup before continuing.
                </p>
            </div>
        ");
    }
}


/*
|--------------------------------------------------------------------------
| GET TABLE COLUMNS
|--------------------------------------------------------------------------
*/

$teacherColumns =
    getColumns(
        $conn,
        "teachers"
    );

$classColumns =
    getColumns(
        $conn,
        "classes"
    );

$subjectColumns =
    getColumns(
        $conn,
        "subjects"
    );

$assignmentColumns =
    getColumns(
        $conn,
        "teacher_class_subjects"
    );


/*
|--------------------------------------------------------------------------
| VERIFY REQUIRED COLUMNS
|--------------------------------------------------------------------------
*/

if (
    !hasColumn(
        $teacherColumns,
        "id"
    )
) {

    die("
        <div style=\"
            font-family:Arial;
            padding:30px;
            color:#8a4b4b;
            background:#fff4f4;
        \">

        <h2>HIBS Reports Database Error</h2>

        <p>
            The <strong>teachers</strong> table does not contain
            the required <strong>id</strong> column.
        </p>

        </div>
    ");
}


if (
    !hasColumn(
        $classColumns,
        "id"
    )
    ||
    !hasColumn(
        $classColumns,
        "class_name"
    )
) {

    die("
        <div style=\"
            font-family:Arial;
            padding:30px;
            color:#8a4b4b;
            background:#fff4f4;
        \">

        <h2>HIBS Reports Database Error</h2>

        <p>
            The <strong>classes</strong> table must contain:
        </p>

        <p>
            <strong>id</strong> and
            <strong>class_name</strong>
        </p>

        </div>
    ");
}


if (
    !hasColumn(
        $subjectColumns,
        "id"
    )
    ||
    !hasColumn(
        $subjectColumns,
        "subject_name"
    )
) {

    die("
        <div style=\"
            font-family:Arial;
            padding:30px;
            color:#8a4b4b;
            background:#fff4f4;
        \">

        <h2>HIBS Reports Database Error</h2>

        <p>
            The <strong>subjects</strong> table must contain:
        </p>

        <p>
            <strong>id</strong> and
            <strong>subject_name</strong>
        </p>

        </div>
    ");
}


$assignmentRequired = [
    "id",
    "teacher_id",
    "class_id",
    "subject_id"
];


foreach (
    $assignmentRequired as $column
) {

    if (
        !hasColumn(
            $assignmentColumns,
            $column
        )
    ) {

        die("
            <div style=\"
                font-family:Arial;
                padding:30px;
                color:#8a4b4b;
                background:#fff4f4;
            \">

            <h2>HIBS Reports Database Error</h2>

            <p>
                The
                <strong>teacher_class_subjects</strong>
                table is missing:
                <strong>" .
                h($column) .
                "</strong>
            </p>

            </div>
        ");
    }
}


/*
|--------------------------------------------------------------------------
| SUCCESS / ERROR
|--------------------------------------------------------------------------
*/

$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| ADD / DELETE ASSIGNMENT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $action =
        $_POST["action"] ?? "";


    try {

        /*
        |--------------------------------------------------------------------------
        | ADD
        |--------------------------------------------------------------------------
        */

        if (
            $action ===
            "add_assignment"
        ) {

            $teacherId =
                filter_input(
                    INPUT_POST,
                    "teacher_id",
                    FILTER_VALIDATE_INT
                );

            $classId =
                filter_input(
                    INPUT_POST,
                    "class_id",
                    FILTER_VALIDATE_INT
                );

            $subjectId =
                filter_input(
                    INPUT_POST,
                    "subject_id",
                    FILTER_VALIDATE_INT
                );


            if (
                !$teacherId ||
                !$classId ||
                !$subjectId
            ) {

                throw new Exception(
                    "Please select a teacher, class and subject."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VERIFY TEACHER
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    SELECT id

                    FROM teachers

                    WHERE id = ?

                    LIMIT 1
                ");

            $stmt->execute([
                $teacherId
            ]);


            if (
                !$stmt->fetchColumn()
            ) {

                throw new Exception(
                    "The selected teacher does not exist."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VERIFY CLASS
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    SELECT id

                    FROM classes

                    WHERE id = ?

                    LIMIT 1
                ");

            $stmt->execute([
                $classId
            ]);


            if (
                !$stmt->fetchColumn()
            ) {

                throw new Exception(
                    "The selected class does not exist."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VERIFY SUBJECT
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    SELECT id

                    FROM subjects

                    WHERE id = ?

                    LIMIT 1
                ");

            $stmt->execute([
                $subjectId
            ]);


            if (
                !$stmt->fetchColumn()
            ) {

                throw new Exception(
                    "The selected subject does not exist."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK DUPLICATE
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    SELECT id

                    FROM teacher_class_subjects

                    WHERE teacher_id = ?

                    AND class_id = ?

                    AND subject_id = ?

                    LIMIT 1
                ");

            $stmt->execute([

                $teacherId,

                $classId,

                $subjectId

            ]);


            if (
                $stmt->fetchColumn()
            ) {

                throw new Exception(
                    "This teacher is already assigned to this class and subject."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | INSERT
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    INSERT INTO teacher_class_subjects
                    (
                        teacher_id,
                        class_id,
                        subject_id
                    )
                    VALUES (?, ?, ?)
                ");

            $stmt->execute([

                $teacherId,

                $classId,

                $subjectId

            ]);


            $success =
                "Teacher assignment created successfully.";
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "delete_assignment"
        ) {

            $assignmentId =
                filter_input(
                    INPUT_POST,
                    "assignment_id",
                    FILTER_VALIDATE_INT
                );


            if (
                !$assignmentId
            ) {

                throw new Exception(
                    "Invalid assignment selected."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DELETE
            |--------------------------------------------------------------------------
            |
            | We deliberately do not query mark_submissions here.
            |
            | The previous version assumed a particular mark_submissions
            | structure and could therefore produce HTTP 500 errors.
            |
            */

            $stmt =
                $conn->prepare("
                    DELETE FROM teacher_class_subjects

                    WHERE id = ?
                ");

            $stmt->execute([
                $assignmentId
            ]);


            if (
                $stmt->rowCount() === 0
            ) {

                throw new Exception(
                    "The assignment could not be found."
                );
            }


            $success =
                "Teacher assignment removed successfully.";
        }


        else {

            throw new Exception(
                "Invalid action."
            );
        }


    } catch (
        Throwable $e
    ) {

        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| TEACHERS
|--------------------------------------------------------------------------
|
| We do NOT assume teachers has a particular name structure.
|
*/

$teachers = [];


try {

    $stmt =
        $conn->query("
            SELECT *
            FROM teachers
            ORDER BY id DESC
        ");

    $teacherRows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    foreach (
        $teacherRows as $teacher
    ) {

        $name = "";


        /*
        |--------------------------------------------------------------------------
        | POSSIBLE FULL NAME
        |--------------------------------------------------------------------------
        */

        $possibleNameColumns = [

            "teacher_name",
            "name",
            "full_name"

        ];


        foreach (
            $possibleNameColumns
            as $column
        ) {

            if (
                hasColumn(
                    $teacherColumns,
                    $column
                )
                &&
                !empty(
                    $teacher[$column]
                )
            ) {

                $name =
                    trim(
                        (string)
                        $teacher[$column]
                    );

                break;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | FIRST / MIDDLE / LAST
        |--------------------------------------------------------------------------
        */

        if (
            $name === ""
        ) {

            $parts = [];


            foreach (
                [
                    "first_name",
                    "middle_name",
                    "last_name"
                ] as $column
            ) {

                if (
                    hasColumn(
                        $teacherColumns,
                        $column
                    )
                    &&
                    !empty(
                        $teacher[$column]
                    )
                ) {

                    $parts[] =
                        trim(
                            (string)
                            $teacher[$column]
                        );
                }
            }


            if (
                count($parts)
            ) {

                $name =
                    implode(
                        " ",
                        $parts
                    );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | USER ID FALLBACK
        |--------------------------------------------------------------------------
        */

        if (
            $name === ""
            &&
            hasColumn(
                $teacherColumns,
                "user_id"
            )
        ) {

            $name =
                "Teacher #"
                .
                (int)
                $teacher["id"];
        }


        /*
        |--------------------------------------------------------------------------
        | FINAL FALLBACK
        |--------------------------------------------------------------------------
        */

        if (
            $name === ""
        ) {

            $name =
                "Teacher #"
                .
                (int)
                $teacher["id"];
        }


        $employeeId = "";


        foreach (
            [
                "employee_id",
                "staff_id",
                "teacher_id"
            ] as $column
        ) {

            if (
                hasColumn(
                    $teacherColumns,
                    $column
                )
                &&
                !empty(
                    $teacher[$column]
                )
            ) {

                $employeeId =
                    (string)
                    $teacher[$column];

                break;
            }
        }


        $teachers[] = [

            "id" =>
                (int)
                $teacher["id"],

            "teacher_name" =>
                $name,

            "employee_id" =>
                $employeeId

        ];
    }


    usort(
        $teachers,
        function (
            $a,
            $b
        ) {

            return strcasecmp(
                $a["teacher_name"],
                $b["teacher_name"]
            );
        }
    );


} catch (
    Throwable $e
) {

    $error =
        "Unable to load teachers: "
        .
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| CLASSES
|--------------------------------------------------------------------------
*/

$classes = [];


try {

    $stmt =
        $conn->query("
            SELECT
                id,
                class_name

            FROM classes

            ORDER BY class_name ASC
        ");

    $classes =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $e
) {

    $error =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| SUBJECTS
|--------------------------------------------------------------------------
*/

$subjects = [];


try {

    $stmt =
        $conn->query("
            SELECT
                id,
                subject_name

            FROM subjects

            ORDER BY subject_name ASC
        ");

    $subjects =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $e
) {

    $error =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| CURRENT ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$assignments = [];


try {

    /*
    |--------------------------------------------------------------------------
    | Fetch assignments first
    |--------------------------------------------------------------------------
    */

    $stmt =
        $conn->query("
            SELECT
                id,
                teacher_id,
                class_id,
                subject_id

            FROM teacher_class_subjects

            ORDER BY id DESC
        ");

    $assignmentRows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | Build lookup arrays
    |--------------------------------------------------------------------------
    */

    $teacherLookup = [];

    foreach (
        $teachers as $teacher
    ) {

        $teacherLookup[
            (int)$teacher["id"]
        ] =
            $teacher;
    }


    $classLookup = [];

    foreach (
        $classes as $class
    ) {

        $classLookup[
            (int)$class["id"]
        ] =
            $class;
    }


    $subjectLookup = [];

    foreach (
        $subjects as $subject
    ) {

        $subjectLookup[
            (int)$subject["id"]
        ] =
            $subject;
    }


    /*
    |--------------------------------------------------------------------------
    | Combine
    |--------------------------------------------------------------------------
    */

    foreach (
        $assignmentRows
        as $assignment
    ) {

        $teacherId =
            (int)
            $assignment[
                "teacher_id"
            ];

        $classId =
            (int)
            $assignment[
                "class_id"
            ];

        $subjectId =
            (int)
            $assignment[
                "subject_id"
            ];


        $assignments[] = [

            "id" =>
                (int)
                $assignment["id"],

            "teacher_name" =>
                $teacherLookup[
                    $teacherId
                ]["teacher_name"]
                ??
                "Teacher #"
                . $teacherId,

            "employee_id" =>
                $teacherLookup[
                    $teacherId
                ]["employee_id"]
                ??
                "",

            "class_name" =>
                $classLookup[
                    $classId
                ]["class_name"]
                ??
                "Class #"
                . $classId,

            "subject_name" =>
                $subjectLookup[
                    $subjectId
                ]["subject_name"]
                ??
                "Subject #"
                . $subjectId

        ];
    }


    usort(
        $assignments,
        function (
            $a,
            $b
        ) {

            $teacherCompare =
                strcasecmp(
                    $a["teacher_name"],
                    $b["teacher_name"]
                );

            if (
                $teacherCompare !== 0
            ) {

                return $teacherCompare;
            }


            $classCompare =
                strcasecmp(
                    $a["class_name"],
                    $b["class_name"]
                );

            if (
                $classCompare !== 0
            ) {

                return $classCompare;
            }


            return strcasecmp(
                $a["subject_name"],
                $b["subject_name"]
            );
        }
    );


} catch (
    Throwable $e
) {

    $error =
        "Unable to load assignments: "
        .
        $e->getMessage();
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
    HIBS Reports | Teacher Assignments
</title>


<style>

* {
    box-sizing: border-box;
}


:root {

    --navy: #263238;

    --navy2: #37474f;

    --slate: #607d8b;

    --background: #f4f5f3;

    --white: #ffffff;

    --line: #dedfdd;

    --text: #27353b;

    --muted: #7c898e;

    --success-bg: #edf5ef;

    --success: #477052;

    --error-bg: #fbefef;

    --error: #914f4f;

}


body {

    margin: 0;

    background:
        var(--background);

    color:
        var(--text);

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

}


/* SIDEBAR */

.sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: 245px;

    height: 100vh;

    background:
        var(--navy);

    color: white;

    padding:
        25px 16px;

    overflow-y: auto;

}


.brand {

    padding:
        3px 11px 24px;

    border-bottom:
        1px solid
        rgba(255,255,255,.12);

    margin-bottom: 22px;

}


.brand-title {

    font-size: 18px;

    font-weight: 700;

    letter-spacing: 1px;

}


.brand-subtitle {

    margin-top: 6px;

    color: #b4bec2;

    font-size: 8px;

    line-height: 1.7;

}


.nav-label {

    margin:
        0 10px 7px;

    color: #879398;

    font-size: 7px;

    text-transform: uppercase;

    letter-spacing: 1px;

    font-weight: 700;

}


.nav a {

    display: flex;

    align-items: center;

    min-height: 39px;

    padding:
        0 11px;

    margin-bottom: 3px;

    color: #dce2e4;

    text-decoration: none;

    font-size: 9px;

    border-radius: 5px;

}


.nav a:hover {

    background:
        var(--navy2);

}


.nav a.active {

    background:
        #536a73;

}


.icon {

    width: 23px;

    font-size: 12px;

}


/* MAIN */

.main {

    margin-left: 245px;

    min-height: 100vh;

}


.topbar {

    height: 70px;

    background:
        var(--white);

    border-bottom:
        1px solid
        var(--line);

    padding:
        0 32px;

    display: flex;

    align-items: center;

}


.topbar h1 {

    margin: 0;

    font-size: 17px;

    font-weight: 600;

}


.content {

    max-width: 1450px;

    padding:
        30px 32px;

}


.page-heading {

    margin-bottom: 22px;

}


.page-heading h2 {

    margin: 0;

    font-size: 23px;

    font-weight: 600;

}


.page-heading p {

    margin:
        7px 0 0;

    color:
        var(--muted);

    font-size: 9px;

}


/* ALERTS */

.alert {

    padding:
        13px 16px;

    margin-bottom: 18px;

    border: 1px solid;

    font-size: 8px;

    line-height: 1.6;

}


.alert.success {

    background:
        var(--success-bg);

    color:
        var(--success);

    border-color:
        #cadfce;

}


.alert.error {

    background:
        var(--error-bg);

    color:
        var(--error);

    border-color:
        #e4cccc;

}


/* FORM */

.panel {

    background:
        var(--white);

    border:
        1px solid
        var(--line);

    margin-bottom: 20px;

}


.panel-header {

    padding:
        17px 20px;

    border-bottom:
        1px solid
        #e8e9e7;

}


.panel-title {

    font-size: 12px;

    font-weight: 600;

}


.panel-description {

    margin-top: 4px;

    color:
        var(--muted);

    font-size: 7px;

}


.form {

    padding: 20px;

}


.form-grid {

    display: grid;

    grid-template-columns:
        1fr
        1fr
        1fr
        auto;

    gap: 12px;

    align-items: end;

}


label {

    display: block;

    margin-bottom: 6px;

    color:
        #69777c;

    font-size: 7px;

    font-weight: 700;

    text-transform: uppercase;

}


select {

    width: 100%;

    height: 39px;

    padding:
        0 10px;

    border:
        1px solid
        #cfd3d1;

    border-radius: 4px;

    background:
        white;

    color:
        var(--text);

    font-size: 8px;

}


.button {

    height: 39px;

    padding:
        0 18px;

    border: 0;

    border-radius: 4px;

    background:
        var(--slate);

    color: white;

    font-size: 8px;

    font-weight: 600;

    cursor: pointer;

}


.button:hover {

    background:
        var(--navy2);

}


/* TABLE */

.table-wrap {

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

}


th {

    padding:
        12px 14px;

    background:
        #f1f2f0;

    color:
        #657277;

    border-bottom:
        1px solid
        var(--line);

    text-align: left;

    font-size: 7px;

    text-transform: uppercase;

    letter-spacing: .4px;

}


td {

    padding:
        13px 14px;

    border-bottom:
        1px solid
        #eceeec;

    font-size: 8px;

}


.teacher-name {

    font-weight: 600;

}


.employee {

    margin-top: 3px;

    color:
        var(--muted);

    font-size: 6px;

}


.assignment-value {

    font-weight: 600;

}


.remove {

    padding:
        6px 10px;

    border:
        1px solid
        #decaca;

    border-radius: 3px;

    background:
        white;

    color:
        #8c5656;

    font-size: 6px;

    cursor: pointer;

}


.remove:hover {

    background:
        #fbf1f1;

}


/* EMPTY */

.empty {

    padding:
        50px 20px;

    text-align: center;

    color:
        var(--muted);

    font-size: 8px;

}


/* INFORMATION */

.info {

    padding:
        17px 19px;

    background:
        #eef1ef;

    border:
        1px solid
        #d8ddda;

    color:
        #617076;

    font-size: 8px;

    line-height: 1.8;

}


/* MOBILE */

@media(max-width:900px) {

    .form-grid {

        grid-template-columns:
            1fr
            1fr;

    }

}


@media(max-width:700px) {

    .sidebar {

        position: static;

        width: 100%;

        height: auto;

    }


    .main {

        margin-left: 0;

    }


    .nav {

        display: grid;

        grid-template-columns:
            1fr 1fr;

    }


    .content {

        padding:
            20px 15px;

    }


    .topbar {

        padding:
            0 15px;

    }


    .form-grid {

        grid-template-columns:
            1fr;

    }


    .button {

        width: 100%;

    }

}

</style>

</head>


<body>


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


<div class="nav-label">
    Administration
</div>


<nav class="nav">


<a href="dashboard.php">

    <span class="icon">▦</span>

    Dashboard

</a>


<a href="academic_setup.php">

    <span class="icon">◫</span>

    Academic Setup

</a>


<a
    href="teacher_assignments.php"
    class="active"
>

    <span class="icon">⟷</span>

    Teacher Assignments

</a>


<a href="students.php">

    <span class="icon">♙</span>

    Students

</a>


<a href="teachers.php">

    <span class="icon">♙</span>

    Teachers

</a>


<a href="classes.php">

    <span class="icon">□</span>

    Classes

</a>


<a href="subjects.php">

    <span class="icon">◇</span>

    Subjects

</a>


<a href="mark_submissions.php">

    <span class="icon">✓</span>

    Mark Submissions

</a>


<a href="reports.php">

    <span class="icon">▤</span>

    Reports

</a>


<a href="report_approval.php">

    <span class="icon">✓</span>

    Report Approval

</a>


<a href="publish_report.php">

    <span class="icon">↑</span>

    Publish Reports

</a>


<a href="analytics.php">

    <span class="icon">◒</span>

    Analytics

</a>


<a href="database_check.php">

    <span class="icon">◉</span>

    Database Check

</a>


<a href="settings.php">

    <span class="icon">⚙</span>

    Settings

</a>


</nav>


</aside>


<main class="main">


<header class="topbar">

    <h1>
        Teacher Assignments
    </h1>

</header>


<div class="content">


<div class="page-heading">

    <h2>
        Teacher Class & Subject Assignments
    </h2>

    <p>
        Assign each teacher to the exact classes and subjects they are responsible for.
    </p>

</div>


<?php if (
    $success !== ""
): ?>

<div class="alert success">

    <?= h(
        $success
    ) ?>

</div>

<?php endif; ?>


<?php if (
    $error !== ""
): ?>

<div class="alert error">

    <strong>
        System message:
    </strong>

    <?= h(
        $error
    ) ?>

</div>

<?php endif; ?>


<!-- CREATE ASSIGNMENT -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Create Teacher Assignment

    </div>

    <div class="panel-description">

        Select the teacher, class and subject.

    </div>

</div>


<form
    method="POST"
    class="form"
>


<input
    type="hidden"
    name="action"
    value="add_assignment"
>


<div class="form-grid">


<div>

<label>
    Teacher
</label>


<select
    name="teacher_id"
    required
>

<option value="">
    Select Teacher
</option>


<?php foreach (
    $teachers
    as $teacher
): ?>

<option
    value="<?= (int)$teacher["id"] ?>"
>

    <?= h(
        $teacher["teacher_name"]
    ) ?>

    <?php if (
        $teacher["employee_id"] !== ""
    ): ?>

        —
        <?= h(
            $teacher["employee_id"]
        ) ?>

    <?php endif; ?>

</option>

<?php endforeach; ?>


</select>

</div>


<div>

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
>

    <?= h(
        $class["class_name"]
    ) ?>

</option>

<?php endforeach; ?>


</select>

</div>


<div>

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
>

    <?= h(
        $subject["subject_name"]
    ) ?>

</option>

<?php endforeach; ?>


</select>

</div>


<div>

<button
    type="submit"
    class="button"
>
    Assign Teacher
</button>

</div>


</div>


</form>


</section>


<!-- CURRENT ASSIGNMENTS -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Current Assignments

    </div>

    <div class="panel-description">

        Each row represents one exact teaching responsibility.

    </div>

</div>


<?php if (
    count($assignments) > 0
): ?>


<div class="table-wrap">


<table>


<thead>

<tr>

<th>
    Teacher
</th>

<th>
    Class
</th>

<th>
    Subject
</th>

<th>
    Action
</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $assignments
    as $assignment
): ?>


<tr>


<td>


<div class="teacher-name">

    <?= h(
        $assignment[
            "teacher_name"
        ]
    ) ?>

</div>


<?php if (
    $assignment[
        "employee_id"
    ] !== ""
): ?>

<div class="employee">

    <?= h(
        $assignment[
            "employee_id"
        ]
    ) ?>

</div>

<?php endif; ?>


</td>


<td>

<div class="assignment-value">

    <?= h(
        $assignment[
            "class_name"
        ]
    ) ?>

</div>

</td>


<td>

<div class="assignment-value">

    <?= h(
        $assignment[
            "subject_name"
        ]
    ) ?>

</div>

</td>


<td>


<form
    method="POST"
    onsubmit="
        return confirm(
            'Are you sure you want to remove this teacher assignment?'
        );
    "
>


<input
    type="hidden"
    name="action"
    value="delete_assignment"
>


<input
    type="hidden"
    name="assignment_id"
    value="<?= (int)$assignment["id"] ?>"
>


<button
    type="submit"
    class="remove"
>
    Remove
</button>


</form>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty">

    No teacher assignments have been created yet.

</div>


<?php endif; ?>


</section>


<div class="info">

<strong>
    Assignment control:
</strong>

A teacher assigned to
<strong>Year 10 → Physics</strong>
will be authorised to work with that exact class and subject.
Assignment to another class or subject must be created separately.

</div>


</div>


</main>


</body>

</html>
