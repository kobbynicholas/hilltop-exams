<?php

session_start();

require_once "../config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| ACADEMIC SETUP & MANAGEMENT
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
| ADMIN SECURITY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}


$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $action =
        trim(
            $_POST["action"] ?? ""
        );


    try {

        $conn->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | CREATE ACADEMIC YEAR
        |--------------------------------------------------------------------------
        */

        if (
            $action ===
            "add_year"
        ) {

            $academicYear =
                trim(
                    $_POST[
                        "academic_year"
                    ] ?? ""
                );


            if (
                $academicYear === ""
            ) {

                throw new Exception(
                    "Academic year is required."
                );
            }


            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM academic_years

                    WHERE academic_year = ?
                ");

            $stmt->execute([
                $academicYear
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This academic year already exists."
                );
            }


            $stmt =
                $conn->prepare("
                    INSERT INTO academic_years
                    (
                        academic_year
                    )
                    VALUES (?)
                ");

            $stmt->execute([
                $academicYear
            ]);


            $success =
                "Academic year added successfully.";
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE ACADEMIC YEAR
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "delete_year"
        ) {

            $id =
                filter_input(
                    INPUT_POST,
                    "id",
                    FILTER_VALIDATE_INT
                );


            if (!$id) {

                throw new Exception(
                    "Invalid academic year."
                );
            }


            /*
            | Check terms
            */

            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM terms

                    WHERE academic_year_id = ?
                ");

            $stmt->execute([
                $id
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This academic year cannot be deleted because it has terms attached to it."
                );
            }


            $stmt =
                $conn->prepare("
                    DELETE FROM academic_years

                    WHERE id = ?
                ");

            $stmt->execute([
                $id
            ]);


            $success =
                "Academic year deleted.";
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE TERM
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "add_term"
        ) {

            $academicYearId =
                filter_input(
                    INPUT_POST,
                    "academic_year_id",
                    FILTER_VALIDATE_INT
                );


            $termName =
                trim(
                    $_POST[
                        "term_name"
                    ] ?? ""
                );


            if (
                !$academicYearId
            ) {

                throw new Exception(
                    "Please select an academic year."
                );
            }


            if (
                $termName === ""
            ) {

                throw new Exception(
                    "Term name is required."
                );
            }


            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM terms

                    WHERE

                        academic_year_id = ?

                        AND term_name = ?
                ");

            $stmt->execute([

                $academicYearId,

                $termName

            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This term already exists for the selected academic year."
                );
            }


            $stmt =
                $conn->prepare("
                    INSERT INTO terms
                    (
                        academic_year_id,
                        term_name
                    )
                    VALUES (?, ?)
                ");

            $stmt->execute([

                $academicYearId,

                $termName

            ]);


            $success =
                "Term added successfully.";
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE TERM
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "delete_term"
        ) {

            $id =
                filter_input(
                    INPUT_POST,
                    "id",
                    FILTER_VALIDATE_INT
                );


            if (!$id) {

                throw new Exception(
                    "Invalid term."
                );
            }


            /*
            | Check reports
            */

            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM report_card_records

                    WHERE term_id = ?
                ");

            $stmt->execute([
                $id
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This term cannot be deleted because reports already exist for it."
                );
            }


            /*
            | Check marks
            */

            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM marks

                    WHERE term_id = ?
                ");

            $stmt->execute([
                $id
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This term cannot be deleted because marks already exist for it."
                );
            }


            $stmt =
                $conn->prepare("
                    DELETE FROM terms

                    WHERE id = ?
                ");

            $stmt->execute([
                $id
            ]);


            $success =
                "Term deleted.";
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE CLASS
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "add_class"
        ) {

            $className =
                trim(
                    $_POST[
                        "class_name"
                    ] ?? ""
                );


            if (
                $className === ""
            ) {

                throw new Exception(
                    "Class name is required."
                );
            }


            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM classes

                    WHERE class_name = ?
                ");

            $stmt->execute([
                $className
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This class already exists."
                );
            }


            $stmt =
                $conn->prepare("
                    INSERT INTO classes
                    (
                        class_name
                    )
                    VALUES (?)
                ");

            $stmt->execute([
                $className
            ]);


            $success =
                "Class added successfully.";
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

            $id =
                filter_input(
                    INPUT_POST,
                    "id",
                    FILTER_VALIDATE_INT
                );


            if (!$id) {

                throw new Exception(
                    "Invalid class."
                );
            }


            /*
            | Check students
            */

            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM students

                    WHERE class_id = ?
                ");

            $stmt->execute([
                $id
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This class cannot be deleted because students are assigned to it."
                );
            }


            /*
            | Check reports
            */

            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM report_card_records

                    WHERE class_id = ?
                ");

            $stmt->execute([
                $id
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This class cannot be deleted because reports already exist for it."
                );
            }


            $stmt =
                $conn->prepare("
                    DELETE FROM classes

                    WHERE id = ?
                ");

            $stmt->execute([
                $id
            ]);


            $success =
                "Class deleted.";
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE SUBJECT
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "add_subject"
        ) {

            $subjectName =
                trim(
                    $_POST[
                        "subject_name"
                    ] ?? ""
                );


            if (
                $subjectName === ""
            ) {

                throw new Exception(
                    "Subject name is required."
                );
            }


            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM subjects

                    WHERE subject_name = ?
                ");

            $stmt->execute([
                $subjectName
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This subject already exists."
                );
            }


            $stmt =
                $conn->prepare("
                    INSERT INTO subjects
                    (
                        subject_name
                    )
                    VALUES (?)
                ");

            $stmt->execute([
                $subjectName
            ]);


            $success =
                "Subject added successfully.";
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE SUBJECT
        |--------------------------------------------------------------------------
        */

        elseif (
            $action ===
            "delete_subject"
        ) {

            $id =
                filter_input(
                    INPUT_POST,
                    "id",
                    FILTER_VALIDATE_INT
                );


            if (!$id) {

                throw new Exception(
                    "Invalid subject."
                );
            }


            /*
            | Check report results
            */

            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM report_card_results

                    WHERE subject_id = ?
                ");

            $stmt->execute([
                $id
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This subject cannot be deleted because academic results already exist for it."
                );
            }


            /*
            | Check marks
            */

            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM marks

                    WHERE subject_id = ?
                ");

            $stmt->execute([
                $id
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This subject cannot be deleted because marks already exist for it."
                );
            }


            $stmt =
                $conn->prepare("
                    DELETE FROM subjects

                    WHERE id = ?
                ");

            $stmt->execute([
                $id
            ]);


            $success =
                "Subject deleted.";
        }


        else {

            throw new Exception(
                "Invalid setup action."
            );
        }


        $conn->commit();


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


/*
|--------------------------------------------------------------------------
| LOAD ACADEMIC YEARS
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->query("
        SELECT

            id,
            academic_year

        FROM academic_years

        ORDER BY id DESC
    ");

$academicYears =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| LOAD TERMS
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->query("
        SELECT

            t.id,

            t.term_name,

            t.academic_year_id,

            ay.academic_year

        FROM terms t

        INNER JOIN academic_years ay
            ON ay.id =
                t.academic_year_id

        ORDER BY

            ay.id DESC,

            t.id ASC
    ");

$termRows =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| LOAD CLASSES
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->query("
        SELECT

            id,
            class_name

        FROM classes

        ORDER BY class_name ASC
    ");

$classRows =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| LOAD SUBJECTS
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->query("
        SELECT

            id,
            subject_name

        FROM subjects

        ORDER BY subject_name ASC
    ");

$subjectRows =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
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
    HIBS | Academic Setup
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

}


.nav-title {

    padding:
        0 10px 7px;

    color: #879399;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

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

}


.topbar-title {

    font-size: 16px;

    font-weight: 600;

}


.content {

    max-width: 1450px;

    padding:
        28px 32px;

}


/* =========================================================
   TITLE
========================================================= */

.page-title {

    margin-bottom: 20px;

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
   ALERT
========================================================= */

.alert {

    margin-bottom: 18px;

    padding:
        13px 15px;

    border: 1px solid;

    font-size: 8px;

}


.success {

    background: #eaf3ed;

    border-color: #cbdccd;

    color: #426b50;

}


.error {

    background: #fbefef;

    border-color: #e0c8c8;

    color: #8b4b4b;

}


/* =========================================================
   GRID
========================================================= */

.grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;

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
        17px 18px;

    border-bottom:
        1px solid
        #e7e5e1;

}


.panel-title {

    font-size: 13px;

    font-weight: 600;

}


.panel-subtitle {

    margin-top: 4px;

    color: #899398;

    font-size: 7px;

}


/* =========================================================
   ADD FORM
========================================================= */

.add-form {

    padding: 15px 18px;

    border-bottom:
        1px solid
        #eceae6;

}


.form-row {

    display: flex;

    gap: 7px;

}


.input,
.select {

    flex: 1;

    height: 35px;

    padding:
        0 9px;

    border:
        1px solid
        #d2d1cc;

    background: #ffffff;

    color: #455a64;

    font-family: inherit;

    font-size: 8px;

    border-radius: 3px;

}


.button {

    height: 35px;

    padding:
        0 13px;

    border: 0;

    border-radius: 3px;

    background: #455a64;

    color: #ffffff;

    font-family: inherit;

    font-size: 7px;

    font-weight: bold;

    cursor: pointer;

}


/* =========================================================
   LIST
========================================================= */

.list {

    max-height: 310px;

    overflow-y: auto;

}


.row {

    min-height: 48px;

    padding:
        9px 18px;

    border-bottom:
        1px solid
        #eceae6;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

}


.row:last-child {

    border-bottom: 0;

}


.row-main {

    min-width: 0;

}


.row-title {

    font-size: 8px;

    font-weight: 600;

}


.row-sub {

    margin-top: 3px;

    color: #8b9699;

    font-size: 6px;

}


.delete {

    flex-shrink: 0;

    padding:
        6px 8px;

    border:
        1px solid
        #decaca;

    background: #ffffff;

    color: #8a5a5a;

    border-radius: 3px;

    font-family: inherit;

    font-size: 6px;

    cursor: pointer;

}


.empty {

    padding:
        25px 18px;

    color: #899398;

    font-size: 8px;

    text-align: center;

}


/* =========================================================
   INFORMATION
========================================================= */

.info {

    grid-column:
        1 / -1;

    padding:
        15px 18px;

    background: #f0f2f0;

    border:
        1px solid
        #d9ddd9;

    color: #637176;

    font-size: 8px;

    line-height: 1.7;

}


.info strong {

    color: #455a64;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:850px) {

    .grid {

        grid-template-columns:
            1fr;

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


    .form-row {

        flex-direction: column;

    }


    .button {

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
    Administration
</div>


<a
    href="dashboard.php"
    class="nav-link"
>
    Dashboard
</a>


<a
    href="reports.php"
    class="nav-link"
>
    Report Approval
</a>


<a
    href="mark_submissions.php"
    class="nav-link"
>
    Mark Submissions
</a>


<a
    href="analytics.php"
    class="nav-link"
>
    Academic Analytics
</a>


<a
    href="academic_setup.php"
    class="nav-link active"
>
    Academic Setup
</a>


<a
    href="students.php"
    class="nav-link"
>
    Students
</a>


<a
    href="teachers.php"
    class="nav-link"
>
    Teachers
</a>


<a
    href="database_check.php"
    class="nav-link"
>
    Database Check
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

        Academic Setup & Management

    </div>

</header>


<main class="content">


<div class="page-title">

    <h1>
        Academic Setup
    </h1>

    <p>
        Manage academic years, terms, classes and subjects.
    </p>

</div>


<?php if (
    $success
): ?>

<div class="alert success">

    <?= h(
        $success
    ) ?>

</div>

<?php endif; ?>


<?php if (
    $error
): ?>

<div class="alert error">

    <?= h(
        $error
    ) ?>

</div>

<?php endif; ?>


<div class="grid">


<!-- =====================================================
     ACADEMIC YEARS
====================================================== -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Academic Years

    </div>

    <div class="panel-subtitle">

        Create the school's academic years.

    </div>

</div>


<form
    method="POST"
    class="add-form"
>


<input
    type="hidden"
    name="action"
    value="add_year"
>


<div class="form-row">


<input
    type="text"
    name="academic_year"
    class="input"
    placeholder="e.g. 2026/2027"
    required
>


<button
    type="submit"
    class="button"
>
    Add Year
</button>


</div>


</form>


<div class="list">


<?php if (
    count($academicYears)
): ?>


<?php foreach (
    $academicYears
    as $year
): ?>


<div class="row">


<div class="row-main">

    <div class="row-title">

        <?= h(
            $year[
                "academic_year"
            ]
        ) ?>

    </div>

</div>


<form
    method="POST"
    onsubmit="
        return confirm(
            'Delete this academic year?'
        );
    "
>


<input
    type="hidden"
    name="action"
    value="delete_year"
>


<input
    type="hidden"
    name="id"
    value="<?= (int)$year["id"] ?>"
>


<button
    type="submit"
    class="delete"
>
    Delete
</button>


</form>


</div>


<?php endforeach; ?>


<?php else: ?>


<div class="empty">

    No academic years created.

</div>


<?php endif; ?>


</div>


</section>


<!-- =====================================================
     TERMS
====================================================== -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Terms

    </div>

    <div class="panel-subtitle">

        Add terms under an academic year.

    </div>

</div>


<form
    method="POST"
    class="add-form"
>


<input
    type="hidden"
    name="action"
    value="add_term"
>


<div class="form-row">


<select
    name="academic_year_id"
    class="select"
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
>

    <?= h(
        $year[
            "academic_year"
        ]
    ) ?>

</option>


<?php endforeach; ?>


</select>


<input
    type="text"
    name="term_name"
    class="input"
    placeholder="e.g. First Term"
    required
>


<button
    type="submit"
    class="button"
>
    Add Term
</button>


</div>


</form>


<div class="list">


<?php if (
    count($termRows)
): ?>


<?php foreach (
    $termRows
    as $term
): ?>


<div class="row">


<div class="row-main">


<div class="row-title">

    <?= h(
        $term[
            "term_name"
        ]
    ) ?>

</div>


<div class="row-sub">

    <?= h(
        $term[
            "academic_year"
        ]
    ) ?>

</div>


</div>


<form
    method="POST"
    onsubmit="
        return confirm(
            'Delete this term?'
        );
    "
>


<input
    type="hidden"
    name="action"
    value="delete_term"
>


<input
    type="hidden"
    name="id"
    value="<?= (int)$term["id"] ?>"
>


<button
    type="submit"
    class="delete"
>
    Delete
</button>


</form>


</div>


<?php endforeach; ?>


<?php else: ?>


<div class="empty">

    No terms created.

</div>


<?php endif; ?>


</div>


</section>


<!-- =====================================================
     CLASSES
====================================================== -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Classes

    </div>

    <div class="panel-subtitle">

        Manage the school's classes.

    </div>

</div>


<form
    method="POST"
    class="add-form"
>


<input
    type="hidden"
    name="action"
    value="add_class"
>


<div class="form-row">


<input
    type="text"
    name="class_name"
    class="input"
    placeholder="e.g. Year 10"
    required
>


<button
    type="submit"
    class="button"
>
    Add Class
</button>


</div>


</form>


<div class="list">


<?php if (
    count($classRows)
): ?>


<?php foreach (
    $classRows
    as $class
): ?>


<div class="row">


<div class="row-main">

    <div class="row-title">

        <?= h(
            $class[
                "class_name"
            ]
        ) ?>

    </div>

</div>


<form
    method="POST"
    onsubmit="
        return confirm(
            'Delete this class?'
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
    name="id"
    value="<?= (int)$class["id"] ?>"
>


<button
    type="submit"
    class="delete"
>
    Delete
</button>


</form>


</div>


<?php endforeach; ?>


<?php else: ?>


<div class="empty">

    No classes created.

</div>


<?php endif; ?>


</div>


</section>


<!-- =====================================================
     SUBJECTS
====================================================== -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Subjects

    </div>

    <div class="panel-subtitle">

        Manage the school's academic subjects.

    </div>

</div>


<form
    method="POST"
    class="add-form"
>


<input
    type="hidden"
    name="action"
    value="add_subject"
>


<div class="form-row">


<input
    type="text"
    name="subject_name"
    class="input"
    placeholder="e.g. Physics"
    required
>


<button
    type="submit"
    class="button"
>
    Add Subject
</button>


</div>


</form>


<div class="list">


<?php if (
    count($subjectRows)
): ?>


<?php foreach (
    $subjectRows
    as $subject
): ?>


<div class="row">


<div class="row-main">

    <div class="row-title">

        <?= h(
            $subject[
                "subject_name"
            ]
        ) ?>

    </div>

</div>


<form
    method="POST"
    onsubmit="
        return confirm(
            'Delete this subject?'
        );
    "
>


<input
    type="hidden"
    name="action"
    value="delete_subject"
>


<input
    type="hidden"
    name="id"
    value="<?= (int)$subject["id"] ?>"
>


<button
    type="submit"
    class="delete"
>
    Delete
</button>


</form>


</div>


<?php endforeach; ?>


<?php else: ?>


<div class="empty">

    No subjects created.

</div>


<?php endif; ?>


</div>


</section>


<!-- =====================================================
     INFORMATION
====================================================== -->

<div class="info">

<strong>Important:</strong>

The system protects academic data that is already being used.

For example, an academic year with existing terms,
a class containing students, or a subject with existing
results cannot simply be deleted.

This prevents accidental destruction of the school's
academic history.

</div>


</div>


</main>


</div>


</body>

</html>
