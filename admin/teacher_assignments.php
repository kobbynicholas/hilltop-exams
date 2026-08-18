<?php

session_start();

require_once "../config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| TEACHER CLASS / SUBJECT ASSIGNMENTS
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
| ADD ASSIGNMENT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $action =
        $_POST["action"] ?? "";


    try {

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
                    "Teacher not found."
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
                    "Class not found."
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
                    "Subject not found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK DUPLICATE
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM teacher_class_subjects

                    WHERE

                        teacher_id = ?

                        AND class_id = ?

                        AND subject_id = ?
                ");

            $stmt->execute([

                $teacherId,

                $classId,

                $subjectId

            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
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
        | DELETE ASSIGNMENT
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
                    "Invalid assignment."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK EXISTING MARK SUBMISSIONS
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    SELECT

                        COUNT(*)

                    FROM mark_submissions ms

                    INNER JOIN teacher_class_subjects a

                        ON a.teacher_id =
                            ms.teacher_id

                        AND a.class_id =
                            ms.class_id

                        AND a.subject_id =
                            ms.subject_id

                    WHERE a.id = ?
                ");

            $stmt->execute([
                $assignmentId
            ]);


            if (
                (int)$stmt->fetchColumn()
                > 0
            ) {

                throw new Exception(
                    "This assignment cannot be removed because mark submissions already exist."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DELETE
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    DELETE FROM teacher_class_subjects

                    WHERE id = ?
                ");

            $stmt->execute([
                $assignmentId
            ]);


            $success =
                "Teacher assignment removed.";
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
*/

$stmt =
    $conn->query("
        SELECT

            t.id,

            t.employee_id,

            CONCAT_WS(
                ' ',
                u.first_name,
                u.last_name
            ) AS teacher_name

        FROM teachers t

        INNER JOIN users u
            ON u.id = t.user_id

        ORDER BY
            teacher_name ASC
    ");

$teachers =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| CLASSES
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

$classes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| SUBJECTS
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

$subjects =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->query("
        SELECT

            a.id,

            a.teacher_id,

            a.class_id,

            a.subject_id,

            CONCAT_WS(
                ' ',
                u.first_name,
                u.last_name
            ) AS teacher_name,

            t.employee_id,

            c.class_name,

            s.subject_name,

            a.created_at

        FROM teacher_class_subjects a

        INNER JOIN teachers t
            ON t.id = a.teacher_id

        INNER JOIN users u
            ON u.id = t.user_id

        INNER JOIN classes c
            ON c.id = a.class_id

        INNER JOIN subjects s
            ON s.id = a.subject_id

        ORDER BY

            teacher_name ASC,

            c.class_name ASC,

            s.subject_name ASC
    ");

$assignments =
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
    HIBS | Teacher Assignments
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


.sidebar {

    position: fixed;

    left: 0;
    top: 0;

    width: 235px;

    height: 100vh;

    padding:
        27px 17px;

    background: #263238;

    color: white;

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


.alert {

    margin-bottom: 18px;

    padding: 13px 15px;

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


.panel {

    margin-bottom: 20px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.panel-header {

    padding:
        17px 20px;

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

    gap: 10px;

    align-items: end;

}


label {

    display: block;

    margin-bottom: 6px;

    color: #68767b;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


select {

    width: 100%;

    height: 37px;

    padding: 0 9px;

    border:
        1px solid
        #d2d1cc;

    background: white;

    color: #455a64;

    font-family: inherit;

    font-size: 8px;

    border-radius: 3px;

}


.button {

    height: 37px;

    padding:
        0 16px;

    border: 0;

    border-radius: 3px;

    background: #455a64;

    color: white;

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

    border-collapse: collapse;

    min-width: 800px;

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

}


.teacher {

    font-weight: 600;

}


.employee {

    margin-top: 3px;

    color: #909a9d;

    font-size: 6px;

}


.assignment {

    font-weight: 600;

}


.remove {

    padding:
        6px 9px;

    border:
        1px solid
        #decaca;

    background: white;

    color: #8a5a5a;

    border-radius: 3px;

    font-family: inherit;

    font-size: 6px;

    cursor: pointer;

}


.empty {

    padding: 55px 20px;

    text-align: center;

    color: #899398;

    font-size: 9px;

}


.info {

    padding:
        16px 18px;

    background: #f0f2f0;

    border:
        1px solid
        #d9ddd9;

    color: #637176;

    font-size: 8px;

    line-height: 1.7;

}


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


    .form-grid {

        grid-template-columns: 1fr;

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
    href="academic_setup.php"
    class="nav-link"
>
    Academic Setup
</a>


<a
    href="teacher_assignments.php"
    class="nav-link active"
>
    Teacher Assignments
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
    href="mark_submissions.php"
    class="nav-link"
>
    Mark Submissions
</a>


<a
    href="reports.php"
    class="nav-link"
>
    Report Approval
</a>


<a
    href="analytics.php"
    class="nav-link"
>
    Academic Analytics
</a>


<a
    href="../logout.php"
    class="logout"
>
    Sign Out
</a>


</aside>


<div class="main">


<header class="topbar">

    <div class="topbar-title">

        Teacher Assignments

    </div>

</header>


<main class="content">


<div class="page-title">

    <h1>
        Teacher Class & Subject Assignments
    </h1>

    <p>
        Give each teacher access only to the classes and subjects they are responsible for.
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


<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Create Assignment

    </div>

    <div class="panel-subtitle">

        Select the exact teacher, class and subject.

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
        $teacher[
            "teacher_name"
        ]
    ) ?>

    <?php if (
        $teacher[
            "employee_id"
        ]
    ): ?>

        —
        <?= h(
            $teacher[
                "employee_id"
            ]
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
        $class[
            "class_name"
        ]
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
        $subject[
            "subject_name"
        ]
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


<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Current Teacher Assignments

    </div>

    <div class="panel-subtitle">

        Every row represents one exact teaching responsibility.

    </div>

</div>


<?php if (
    count($assignments)
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
        Assigned
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


<div class="teacher">

    <?= h(
        $assignment[
            "teacher_name"
        ]
    ) ?>

</div>


<div class="employee">

    <?= h(
        $assignment[
            "employee_id"
        ]
        ??
        "No Employee ID"
    ) ?>

</div>


</td>


<td class="assignment">

    <?= h(
        $assignment[
            "class_name"
        ]
    ) ?>

</td>


<td class="assignment">

    <?= h(
        $assignment[
            "subject_name"
        ]
    ) ?>

</td>


<td>

    <?= h(
        date(
            "d M Y",
            strtotime(
                $assignment[
                    "created_at"
                ]
            )
        )
    ) ?>

</td>


<td>


<form
    method="POST"
    onsubmit="
        return confirm(
            'Remove this teacher assignment?'
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

<strong>How this works:</strong>

If a teacher is assigned to
<strong>Year 10 → Physics</strong>,
that teacher will be authorised to enter Physics marks
for Year 10.

Being assigned to another class or another subject
does not automatically grant access to this combination.

</div>


</main>


</div>


</body>

</html>
