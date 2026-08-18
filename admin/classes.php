<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";

ini_set("display_errors", "1");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| ADMIN - CLASS MANAGEMENT
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| ADMIN SECURITY
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION["user_id"]) ||
    strtolower(
        trim(
            (string)(
                $_SESSION["role"] ?? ""
            )
        )
    ) !== "admin"
) {

    header(
        "Location: ../login.php"
    );

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
| ADMIN NAME
|--------------------------------------------------------------------------
*/

$adminName =
    trim(
        (string)(
            $_SESSION["full_name"]
            ??
            ""
        )
    );


if (
    $adminName === ""
) {

    $adminName =
        trim(
            (string)(
                $_SESSION["username"]
                ??
                "Administrator"
            )
        );
}


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$success = "";

$error = "";


/*
|--------------------------------------------------------------------------
| VERIFY CLASSES TABLE
|--------------------------------------------------------------------------
*/

try {

    $check =
        $conn->query("
            SELECT COUNT(*)

            FROM information_schema.tables

            WHERE table_schema = DATABASE()

            AND table_name = 'classes'
        ");


    if (
        (int)$check->fetchColumn() === 0
    ) {

        throw new Exception(
            "The classes table does not exist in the hibs_reports database."
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
| GET CLASS TABLE COLUMNS
|--------------------------------------------------------------------------
*/

$classColumns = [];

if (
    $error === ""
) {

    try {

        $stmt =
            $conn->query("
                SELECT column_name

                FROM information_schema.columns

                WHERE table_schema = DATABASE()

                AND table_name = 'classes'

                ORDER BY ordinal_position
            ");


        $classColumns =
            $stmt->fetchAll(
                PDO::FETCH_COLUMN
            );

    } catch (
        Throwable $e
    ) {

        $error =
            "Unable to inspect the classes table: "
            .
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| COLUMN CHECK
|--------------------------------------------------------------------------
*/

$hasClassName =
    in_array(
        "class_name",
        $classColumns,
        true
    );


$hasClassLevel =
    in_array(
        "class_level",
        $classColumns,
        true
    );


$hasCreatedAt =
    in_array(
        "created_at",
        $classColumns,
        true
    );


$hasUpdatedAt =
    in_array(
        "updated_at",
        $classColumns,
        true
    );


/*
|--------------------------------------------------------------------------
| CREATE / UPDATE / DELETE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    $error === ""
) {

    $action =
        trim(
            (string)(
                $_POST["action"]
                ??
                ""
            )
        );


    try {

        /*
        |--------------------------------------------------------------------------
        | ADD CLASS
        |--------------------------------------------------------------------------
        */

        if (
            $action ===
            "add_class"
        ) {

            if (
                !$hasClassName
            ) {

                throw new Exception(
                    "The classes table does not contain a class_name column."
                );
            }


            $className =
                trim(
                    (string)(
                        $_POST["class_name"]
                        ??
                        ""
                    )
                );


            $classLevel =
                trim(
                    (string)(
                        $_POST["class_level"]
                        ??
                        ""
                    )
                );


            if (
                $className === ""
            ) {

                throw new Exception(
                    "Please enter the class name."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK DUPLICATE
            |--------------------------------------------------------------------------
            */

            if (
                $hasClassLevel
            ) {

                $stmt =
                    $conn->prepare("
                        SELECT id

                        FROM classes

                        WHERE LOWER(class_name)
                            = LOWER(?)

                        AND LOWER(
                            COALESCE(
                                class_level,
                                ''
                            )
                        )
                            = LOWER(?)

                        LIMIT 1
                    ");


                $stmt->execute([
                    $className,
                    $classLevel
                ]);

            } else {

                $stmt =
                    $conn->prepare("
                        SELECT id

                        FROM classes

                        WHERE LOWER(class_name)
                            = LOWER(?)

                        LIMIT 1
                    ");


                $stmt->execute([
                    $className
                ]);
            }


            if (
                $stmt->fetchColumn()
            ) {

                throw new Exception(
                    "This class already exists."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | BUILD INSERT DYNAMICALLY
            |--------------------------------------------------------------------------
            */

            $columns = [
                "class_name"
            ];


            $values = [
                $className
            ];


            if (
                $hasClassLevel
            ) {

                $columns[] =
                    "class_level";

                $values[] =
                    $classLevel;
            }


            if (
                $hasCreatedAt
            ) {

                $columns[] =
                    "created_at";

                $values[] =
                    date(
                        "Y-m-d H:i:s"
                    );
            }


            $columnSql =
                implode(
                    ", ",
                    array_map(
                        function (
                            $column
                        ) {

                            return "`"
                                .
                                $column
                                .
                                "`";

                        },
                        $columns
                    )
                );


            $placeholders =
                implode(
                    ", ",
                    array_fill(
                        0,
                        count($values),
                        "?"
                    )
                );


            $sql = "
                INSERT INTO classes
                (
                    $columnSql
                )

                VALUES
                (
                    $placeholders
                )
            ";


            $stmt =
                $conn->prepare(
                    $sql
                );


            $stmt->execute(
                $values
            );


            $success =
                "Class added successfully.";
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE CLASS
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "update_class"
        ) {

            $classId =
                filter_input(
                    INPUT_POST,
                    "class_id",
                    FILTER_VALIDATE_INT
                );


            $className =
                trim(
                    (string)(
                        $_POST["class_name"]
                        ??
                        ""
                    )
                );


            $classLevel =
                trim(
                    (string)(
                        $_POST["class_level"]
                        ??
                        ""
                    )
                );


            if (
                !$classId
            ) {

                throw new Exception(
                    "Invalid class selected."
                );
            }


            if (
                $className === ""
            ) {

                throw new Exception(
                    "Please enter the class name."
                );
            }


            if (
                !$hasClassName
            ) {

                throw new Exception(
                    "The classes table does not contain class_name."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DUPLICATE CHECK
            |--------------------------------------------------------------------------
            */

            if (
                $hasClassLevel
            ) {

                $stmt =
                    $conn->prepare("
                        SELECT id

                        FROM classes

                        WHERE LOWER(class_name)
                            = LOWER(?)

                        AND LOWER(
                            COALESCE(
                                class_level,
                                ''
                            )
                        )
                            = LOWER(?)

                        AND id <> ?

                        LIMIT 1
                    ");


                $stmt->execute([
                    $className,
                    $classLevel,
                    $classId
                ]);

            } else {

                $stmt =
                    $conn->prepare("
                        SELECT id

                        FROM classes

                        WHERE LOWER(class_name)
                            = LOWER(?)

                        AND id <> ?

                        LIMIT 1
                    ");


                $stmt->execute([
                    $className,
                    $classId
                ]);
            }


            if (
                $stmt->fetchColumn()
            ) {

                throw new Exception(
                    "Another class with the same name already exists."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | BUILD UPDATE
            |--------------------------------------------------------------------------
            */

            $setParts = [
                "class_name = ?"
            ];


            $values = [
                $className
            ];


            if (
                $hasClassLevel
            ) {

                $setParts[] =
                    "class_level = ?";

                $values[] =
                    $classLevel;
            }


            if (
                $hasUpdatedAt
            ) {

                $setParts[] =
                    "updated_at = ?";

                $values[] =
                    date(
                        "Y-m-d H:i:s"
                    );
            }


            $values[] =
                $classId;


            $sql = "
                UPDATE classes

                SET
                    "
                    .
                    implode(
                        ", ",
                        $setParts
                    )
                    .

                    "

                WHERE id = ?
            ";


            $stmt =
                $conn->prepare(
                    $sql
                );


            $stmt->execute(
                $values
            );


            $success =
                "Class updated successfully.";
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE CLASS
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "delete_class"
        ) {

            $classId =
                filter_input(
                    INPUT_POST,
                    "class_id",
                    FILTER_VALIDATE_INT
                );


            if (
                !$classId
            ) {

                throw new Exception(
                    "Invalid class selected."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK WHETHER STUDENTS ARE USING CLASS
            |--------------------------------------------------------------------------
            */

            $studentsUsingClass =
                false;


            try {

                $studentCheck =
                    $conn->prepare("
                        SELECT COUNT(*)

                        FROM students

                        WHERE class_id = ?
                    ");


                $studentCheck->execute([
                    $classId
                ]);


                $studentsUsingClass =
                    (int)
                    $studentCheck->fetchColumn()
                    >
                    0;

            } catch (
                Throwable $e
            ) {

                /*
                |--------------------------------------------------------------------------
                | If students.class_id doesn't exist,
                | simply continue.
                |--------------------------------------------------------------------------
                */
            }


            if (
                $studentsUsingClass
            ) {

                throw new Exception(
                    "This class cannot be deleted because students are currently assigned to it."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DELETE
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    DELETE FROM classes

                    WHERE id = ?
                ");


            $stmt->execute([
                $classId
            ]);


            $success =
                "Class deleted successfully.";
        }


        else {

            throw new Exception(
                "Invalid class management action."
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
| LOAD CLASSES
|--------------------------------------------------------------------------
*/

$classes = [];


if (
    $error === ""
    ||
    $success !== ""
) {

    try {

        $selectFields =
            "id, class_name";


        if (
            $hasClassLevel
        ) {

            $selectFields .=
                ", class_level";
        }


        if (
            $hasCreatedAt
        ) {

            $selectFields .=
                ", created_at";
        }


        if (
            $hasUpdatedAt
        ) {

            $selectFields .=
                ", updated_at";
        }


        $stmt =
            $conn->query("
                SELECT
                    $selectFields

                FROM classes

                ORDER BY
                    class_name ASC
            ");


        $classes =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (
        Throwable $e
    ) {

        $error =
            "Unable to load classes: "
            .
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| STUDENT COUNTS PER CLASS
|--------------------------------------------------------------------------
*/

$classStudentCounts = [];


try {

    $studentTableCheck =
        $conn->query("
            SELECT COUNT(*)

            FROM information_schema.tables

            WHERE table_schema = DATABASE()

            AND table_name = 'students'
        ");


    if (
        (int)$studentTableCheck->fetchColumn() > 0
    ) {

        $stmt =
            $conn->query("
                SELECT
                    class_id,
                    COUNT(*) AS total

                FROM students

                WHERE class_id IS NOT NULL

                GROUP BY class_id
            ");


        $rows =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        foreach (
            $rows as $row
        ) {

            $classStudentCounts[
                (int)$row["class_id"]
            ] =
                (int)$row["total"];
        }
    }

} catch (
    Throwable $e
) {

    /*
    |--------------------------------------------------------------------------
    | Don't break the class page if the student
    | table has a different structure.
    |--------------------------------------------------------------------------
    */
}


/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/

$totalClasses =
    count(
        $classes
    );


$totalStudents =
    0;


foreach (
    $classStudentCounts
    as $count
) {

    $totalStudents +=
        $count;
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
HIBS Reports | Classes
</title>


<style>

* {
    box-sizing: border-box;
}


:root {

    --wine: #68182c;

    --wine-dark: #501321;

    --gold: #b18a3b;

    --cream: #f6f3ed;

    --white: #ffffff;

    --text: #2d2a28;

    --muted: #77716c;

    --line: #ded9d0;

    --success: #3e6b4c;

    --success-bg: #edf5ef;

    --danger: #914848;

    --danger-bg: #faeeee;

}


/*
|--------------------------------------------------------------------------
| BODY
|--------------------------------------------------------------------------
*/

body {

    margin: 0;

    background:
        var(--cream);

    color:
        var(--text);

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    font-size: 15px;

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.top-header {

    min-height: 106px;

    padding:
        18px 42px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    background:
        white;

    border-bottom:
        1px solid
        var(--line);

}


.school-brand {

    display: flex;

    align-items: center;

    gap: 14px;

}


.logo-box {

    width: 50px;

    height: 50px;

    display: flex;

    align-items: center;

    justify-content: center;

    border:
        2px solid
        var(--gold);

    color:
        var(--wine);

    font-family:
        Georgia,
        serif;

    font-size: 27px;

    font-weight: bold;

}


.school-title {

    color:
        var(--wine);

    font-family:
        Georgia,
        serif;

    font-size: 22px;

    font-weight: bold;

    letter-spacing:
        .4px;

}


.school-subtitle {

    margin-top: 3px;

    color:
        var(--muted);

    font-size: 10px;

    letter-spacing:
        1.2px;

}


.user-area {

    text-align: right;

}


.user-name {

    color:
        var(--wine);

    font-size: 13px;

    font-weight: 700;

}


.user-role {

    margin-top: 5px;

    color:
        var(--muted);

    font-size: 10px;

}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

.navbar {

    min-height: 48px;

    display: flex;

    align-items: center;

    padding:
        0 42px;

    background:
        var(--wine);

}


.navbar a {

    display: flex;

    align-items: center;

    min-height: 48px;

    padding:
        0 18px;

    color:
        white;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

}


.navbar a:hover {

    background:
        rgba(
            255,
            255,
            255,
            .09
        );

}


.navbar a.active {

    background:
        var(--wine-dark);

}


/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

.main {

    max-width: 1320px;

    margin:
        0 auto;

    padding:
        42px 30px 70px;

}


.page-heading {

    margin-bottom: 28px;

}


.page-heading h1 {

    margin: 0;

    color:
        var(--wine);

    font-family:
        Georgia,
        serif;

    font-size: 31px;

    font-weight: 500;

}


.page-heading p {

    margin:
        7px 0 0;

    color:
        var(--muted);

    font-size: 14px;

}


/*
|--------------------------------------------------------------------------
| ALERT
|--------------------------------------------------------------------------
*/

.alert {

    margin-bottom: 22px;

    padding:
        15px 18px;

    border:
        1px solid;

    font-size: 13px;

    line-height: 1.5;

}


.alert.success {

    color:
        var(--success);

    background:
        var(--success-bg);

    border-color:
        #c8ddcd;

}


.alert.error {

    color:
        var(--danger);

    background:
        var(--danger-bg);

    border-color:
        #e4cccc;

}


/*
|--------------------------------------------------------------------------
| SUMMARY CARDS
|--------------------------------------------------------------------------
*/

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 28px;

}


.summary-card {

    padding:
        22px 24px;

    background:
        white;

    border:
        1px solid
        var(--line);

}


.summary-label {

    color:
        var(--muted);

    font-size: 11px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing:
        .8px;

}


.summary-number {

    margin-top: 8px;

    color:
        var(--wine);

    font-size: 30px;

    font-weight: 700;

}


/*
|--------------------------------------------------------------------------
| PANEL
|--------------------------------------------------------------------------
*/

.panel {

    margin-bottom: 25px;

    background:
        white;

    border:
        1px solid
        var(--line);

}


.panel-header {

    padding:
        20px 24px;

    border-bottom:
        1px solid
        var(--line);

}


.panel-header h2 {

    margin: 0;

    color:
        var(--wine);

    font-family:
        Georgia,
        serif;

    font-size: 20px;

    font-weight: 500;

}


.panel-header p {

    margin:
        6px 0 0;

    color:
        var(--muted);

    font-size: 12px;

}


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.form-body {

    padding:
        25px;

}


.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;

}


.form-group {

    min-width: 0;

}


label {

    display: block;

    margin-bottom: 8px;

    color:
        #514a45;

    font-size: 12px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing:
        .4px;

}


input {

    width: 100%;

    height: 46px;

    padding:
        0 13px;

    border:
        1px solid
        #cbc5bc;

    background:
        white;

    color:
        var(--text);

    font-family:
        inherit;

    font-size: 14px;

}


input:focus {

    outline: none;

    border-color:
        var(--wine);

    box-shadow:
        0 0 0 2px
        rgba(
            104,
            24,
            44,
            .08
        );

}


.form-actions {

    margin-top: 22px;

}


.btn {

    min-height: 40px;

    padding:
        0 17px;

    border: 0;

    font-family:
        inherit;

    font-size: 12px;

    font-weight: 700;

    cursor: pointer;

}


.btn-primary {

    background:
        var(--wine);

    color:
        white;

}


.btn-primary:hover {

    background:
        var(--wine-dark);

}


.btn-secondary {

    background:
        #eeeae3;

    color:
        var(--text);

}


.btn-danger {

    background:
        white;

    color:
        var(--danger);

    border:
        1px solid
        #ddc5c5;

}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

.search-row {

    padding:
        18px 24px;

    border-bottom:
        1px solid
        var(--line);

}


.search-input {

    max-width: 390px;

}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrap {

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

}


thead th {

    padding:
        14px 18px;

    background:
        #f1eee8;

    color:
        #625b55;

    border-bottom:
        1px solid
        var(--line);

    text-align: left;

    font-size: 11px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing:
        .5px;

}


tbody td {

    padding:
        17px 18px;

    border-bottom:
        1px solid
        #ece8e1;

    vertical-align: middle;

    font-size: 13px;

}


tbody tr:hover {

    background:
        #fbfaf7;

}


.class-name {

    color:
        var(--wine);

    font-weight: 700;

}


.class-level {

    color:
        var(--muted);

}


.student-count {

    display: inline-flex;

    min-width: 32px;

    padding:
        5px 9px;

    justify-content: center;

    background:
        #f0ebe3;

    color:
        var(--wine);

    font-size: 11px;

    font-weight: 700;

}


.actions {

    display: flex;

    gap: 7px;

    align-items: center;

}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    padding:
        55px 20px;

    text-align: center;

}


.empty-title {

    color:
        var(--wine);

    font-family:
        Georgia,
        serif;

    font-size: 20px;

}


.empty-text {

    margin-top: 7px;

    color:
        var(--muted);

    font-size: 13px;

}


/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

.modal {

    position: fixed;

    inset: 0;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background:
        rgba(
            0,
            0,
            0,
            .42
        );

    z-index: 1000;

}


.modal.show {

    display: flex;

}


.modal-box {

    width: 100%;

    max-width: 530px;

    background:
        white;

    border:
        1px solid
        var(--line);

}


.modal-header {

    padding:
        20px 24px;

    border-bottom:
        1px solid
        var(--line);

}


.modal-header h3 {

    margin: 0;

    color:
        var(--wine);

    font-family:
        Georgia,
        serif;

    font-size: 20px;

    font-weight: 500;

}


.modal-body {

    padding:
        24px;

}


.modal-footer {

    display: flex;

    justify-content: flex-end;

    gap: 9px;

    padding:
        16px 24px;

    border-top:
        1px solid
        var(--line);

}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 900px
) {

    .top-header {

        padding:
            17px 22px;

    }


    .navbar {

        padding:
            0 15px;

        overflow-x: auto;

    }


    .navbar a {

        padding:
            0 13px;

        white-space: nowrap;

    }


    .main {

        padding:
            30px 18px 55px;

    }


    .summary-grid {

        grid-template-columns:
            1fr;

    }


    .form-grid {

        grid-template-columns:
            1fr;

    }

}


@media (
    max-width: 600px
) {

    .top-header {

        flex-direction:
            column;

        align-items:
            flex-start;

        gap: 15px;

    }


    .user-area {

        text-align:
            left;

    }


    .school-title {

        font-size: 19px;

    }


    .page-heading h1 {

        font-size: 26px;

    }


    .actions {

        flex-direction:
            column;

        align-items:
            stretch;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
====================================================== -->

<header class="top-header">


<div class="school-brand">


<div class="logo-box">
    H
</div>


<div>

<div class="school-title">

    HIBS REPORTS

</div>


<div class="school-subtitle">

    HILLTOP INTERNATIONAL BRITISH SCHOOL

</div>

</div>


</div>


<div class="user-area">


<div class="user-name">

    <?= h(
        $adminName
    ) ?>

</div>


<div class="user-role">

    Administrator

    &nbsp; | &nbsp;

    <a
        href="../logout.php"
        style="
            color:#68182c;
            text-decoration:none;
        "
    >
        Sign out
    </a>

</div>


</div>


</header>


<!-- =====================================================
     NAVIGATION
====================================================== -->

<nav class="navbar">


<a href="dashboard.php">
    Dashboard
</a>


<a href="students.php">
    Students
</a>


<a
    href="classes.php"
    class="active"
>
    Classes
</a>


<a href="subjects.php">
    Subjects
</a>


<a href="teachers.php">
    Teachers
</a>


<a href="marks.php">
    Marks
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

<main class="main">


<div class="page-heading">


<h1>
    Classes
</h1>


<p>
    Manage HIBS academic classes and year groups.
</p>


</div>


<!-- ALERTS -->


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

    <?= h(
        $error
    ) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     SUMMARY
====================================================== -->

<section class="summary-grid">


<div class="summary-card">

<div class="summary-label">

    Total Classes

</div>


<div class="summary-number">

    <?= number_format(
        $totalClasses
    ) ?>

</div>


</div>


<div class="summary-card">

<div class="summary-label">

    Students Assigned

</div>


<div class="summary-number">

    <?= number_format(
        $totalStudents
    ) ?>

</div>


</div>


<div class="summary-card">

<div class="summary-label">

    Academic Structure

</div>


<div class="summary-number">

    HIBS

</div>


</div>


</section>


<!-- =====================================================
     ADD CLASS
====================================================== -->

<section class="panel">


<div class="panel-header">


<h2>
    Add New Class
</h2>


<p>
    Create an academic class or year group for students.
</p>


</div>


<form
    method="POST"
    class="form-body"
>


<input
    type="hidden"
    name="action"
    value="add_class"
>


<div class="form-grid">


<div class="form-group">


<label for="class_name">

    Class Name

</label>


<input
    type="text"
    id="class_name"
    name="class_name"
    placeholder="e.g. Year 7"
    required
>


</div>


<?php if (
    $hasClassLevel
): ?>


<div class="form-group">


<label for="class_level">

    Class Level

</label>


<input
    type="text"
    id="class_level"
    name="class_level"
    placeholder="e.g. Lower Secondary"
>


</div>


<?php endif; ?>


</div>


<div class="form-actions">


<button
    type="submit"
    class="btn btn-primary"
>

    + Add Class

</button>


</div>


</form>


</section>


<!-- =====================================================
     CLASS LIST
====================================================== -->

<section class="panel">


<div class="panel-header">


<h2>
    Existing Classes
</h2>


<p>
    Classes currently configured in the HIBS academic reporting system.
</p>


</div>


<div class="search-row">


<input
    type="text"
    id="classSearch"
    class="search-input"
    placeholder="Search classes..."
    onkeyup="searchClasses()"
>


</div>


<div class="table-wrap">


<?php if (
    count($classes) > 0
): ?>


<table id="classesTable">


<thead>

<tr>

<th>
    #
</th>

<th>
    Class
</th>

<?php if (
    $hasClassLevel
): ?>

<th>
    Level
</th>

<?php endif; ?>


<th>
    Students
</th>


<th>
    Actions
</th>

</tr>

</thead>


<tbody>


<?php

$counter = 1;

foreach (
    $classes as $class
):


$classId =
    (int)(
        $class["id"]
        ?? 0
    );


$className =
    (string)(
        $class["class_name"]
        ?? ""
    );


$classLevel =
    (string)(
        $class["class_level"]
        ?? ""
    );


$studentCount =
    $classStudentCounts[
        $classId
    ]
    ??
    0;

?>


<tr>


<td>

    <?= $counter++ ?>

</td>


<td>


<div class="class-name">

    <?= h(
        $className
    ) ?>

</div>


<?php if (
    !empty(
        $class["created_at"]
        ?? ""
    )
): ?>

<div
    style="
        margin-top:4px;
        color:#999;
        font-size:10px;
    "
>

    Created
    <?= h(
        date(
            "d M Y",
            strtotime(
                (string)
                $class["created_at"]
            )
        )
    ) ?>

</div>

<?php endif; ?>


</td>


<?php if (
    $hasClassLevel
): ?>

<td>


<span class="class-level">

    <?= h(
        $classLevel
        ?: "—"
    ) ?>

</span>


</td>

<?php endif; ?>


<td>


<span class="student-count">

    <?= number_format(
        $studentCount
    ) ?>

</span>


</td>


<td>


<div class="actions">


<button
    type="button"
    class="btn btn-secondary"
    onclick="
        openEditModal(
            <?= $classId ?>,
            '<?= h(
                $className
            ) ?>',
            '<?= h(
                $classLevel
            ) ?>'
        )
    "
>

    Edit

</button>


<form
    method="POST"
    style="display:inline;"
    onsubmit="
        return confirm(
            'Are you sure you want to delete <?= h($className) ?>?'
        );
    "
>


<input
    type="hidden"
    name="action"
    value="delete_class"
>


<input
    type="hidden"
    name="class_id"
    value="<?= $classId ?>"
>


<button
    type="submit"
    class="btn btn-danger"
>

    Delete

</button>


</form>


</div>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


<?php else: ?>


<div class="empty">


<div class="empty-title">

    No Classes Yet

</div>


<div class="empty-text">

    Use the form above to create your first academic class.

</div>


</div>


<?php endif; ?>


</div>


</section>


</main>


<!-- =====================================================
     EDIT MODAL
====================================================== -->

<div
    class="modal"
    id="editModal"
>


<div class="modal-box">


<div class="modal-header">


<h3>
    Edit Class
</h3>


</div>


<form
    method="POST"
>


<input
    type="hidden"
    name="action"
    value="update_class"
>


<input
    type="hidden"
    name="class_id"
    id="editClassId"
>


<div class="modal-body">


<div class="form-group">


<label for="editClassName">

    Class Name

</label>


<input
    type="text"
    name="class_name"
    id="editClassName"
    required
>


</div>


<?php if (
    $hasClassLevel
): ?>


<div
    class="form-group"
    style="margin-top:18px;"
>


<label for="editClassLevel">

    Class Level

</label>


<input
    type="text"
    name="class_level"
    id="editClassLevel"
>


</div>


<?php endif; ?>


</div>


<div class="modal-footer">


<button
    type="button"
    class="btn btn-secondary"
    onclick="closeEditModal()"
>

    Cancel

</button>


<button
    type="submit"
    class="btn btn-primary"
>

    Save Changes

</button>


</div>


</form>


</div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| SEARCH CLASSES
|--------------------------------------------------------------------------
*/

function searchClasses() {

    const input =
        document.getElementById(
            "classSearch"
        );


    const filter =
        input.value
            .toLowerCase()
            .trim();


    const table =
        document.getElementById(
            "classesTable"
        );


    if (
        !table
    ) {

        return;
    }


    const rows =
        table
            .getElementsByTagName(
                "tbody"
            )[0]
            .getElementsByTagName(
                "tr"
            );


    for (
        let i = 0;
        i < rows.length;
        i++
    ) {

        const text =
            rows[i]
                .innerText
                .toLowerCase();


        rows[i].style.display =
            text.includes(
                filter
            )
            ? ""
            : "none";
    }
}


/*
|--------------------------------------------------------------------------
| OPEN EDIT MODAL
|--------------------------------------------------------------------------
*/

function openEditModal(
    id,
    name,
    level
) {

    document.getElementById(
        "editClassId"
    ).value =
        id;


    document.getElementById(
        "editClassName"
    ).value =
        name;


    const levelInput =
        document.getElementById(
            "editClassLevel"
        );


    if (
        levelInput
    ) {

        levelInput.value =
            level;
    }


    document.getElementById(
        "editModal"
    ).classList.add(
        "show"
    );
}


/*
|--------------------------------------------------------------------------
| CLOSE EDIT MODAL
|--------------------------------------------------------------------------
*/

function closeEditModal() {

    document.getElementById(
        "editModal"
    ).classList.remove(
        "show"
    );
}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL WHEN CLICKING OUTSIDE
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        "editModal"
    )
    .addEventListener(
        "click",
        function(event) {

            if (
                event.target ===
                this
            ) {

                closeEditModal();
            }

        }
    );


/*
|--------------------------------------------------------------------------
| ESC KEY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key ===
            "Escape"
        ) {

            closeEditModal();
        }

    }
);

</script>


</body>

</html>
