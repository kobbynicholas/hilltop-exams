<?php

session_start();

require_once "../config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS MARK SUBMISSION
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
| SECURITY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "teacher"
) {

    header("Location: ../login.php");
    exit;
}


$userId = (int)$_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| TEACHER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id
    FROM teachers
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([
    $userId
]);

$teacher = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$teacher) {
    die("Teacher profile not found.");
}


$teacherId = (int)$teacher["id"];


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$academicYearId = filter_input(
    INPUT_GET,
    "academic_year_id",
    FILTER_VALIDATE_INT
);

$termId = filter_input(
    INPUT_GET,
    "term_id",
    FILTER_VALIDATE_INT
);

$classId = filter_input(
    INPUT_GET,
    "class_id",
    FILTER_VALIDATE_INT
);

$subjectId = filter_input(
    INPUT_GET,
    "subject_id",
    FILTER_VALIDATE_INT
);


if (
    !$academicYearId ||
    !$termId ||
    !$classId ||
    !$subjectId
) {

    die("Incomplete submission information.");
}


$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| VERIFY TEACHER ASSIGNMENT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM teacher_classes
    WHERE teacher_id = ?
    AND class_id = ?
");

$stmt->execute([
    $teacherId,
    $classId
]);

$classAssigned =
    (int)$stmt->fetchColumn();


$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM teacher_subjects
    WHERE teacher_id = ?
    AND subject_id = ?
");

$stmt->execute([
    $teacherId,
    $subjectId
]);

$subjectAssigned =
    (int)$stmt->fetchColumn();


if (
    !$classAssigned ||
    !$subjectAssigned
) {

    die(
        "You are not authorised to submit marks for this class and subject."
    );
}


/*
|--------------------------------------------------------------------------
| LOAD CLASS / SUBJECT INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT class_name
    FROM classes
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $classId
]);

$className =
    $stmt->fetchColumn();


$stmt = $conn->prepare("
    SELECT subject_name
    FROM subjects
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $subjectId
]);

$subjectName =
    $stmt->fetchColumn();


$stmt = $conn->prepare("
    SELECT term_name
    FROM terms
    WHERE id = ?
    AND academic_year_id = ?
    LIMIT 1
");

$stmt->execute([
    $termId,
    $academicYearId
]);

$termName =
    $stmt->fetchColumn();


$stmt = $conn->prepare("
    SELECT academic_year
    FROM academic_years
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $academicYearId
]);

$academicYear =
    $stmt->fetchColumn();


if (
    !$className ||
    !$subjectName ||
    !$termName ||
    !$academicYear
) {

    die("Invalid academic selection.");
}


/*
|--------------------------------------------------------------------------
| EXISTING SUBMISSION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM mark_submissions
    WHERE teacher_id = ?
    AND term_id = ?
    AND class_id = ?
    AND subject_id = ?
    LIMIT 1
");

$stmt->execute([
    $teacherId,
    $termId,
    $classId,
    $subjectId
]);

$submission =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| CREATE DRAFT IF NECESSARY
|--------------------------------------------------------------------------
*/

if (!$submission) {

    $stmt = $conn->prepare("
        INSERT INTO mark_submissions (

            teacher_id,
            academic_year_id,
            term_id,
            class_id,
            subject_id,
            status

        )

        VALUES (?, ?, ?, ?, ?, 'Draft')
    ");

    $stmt->execute([

        $teacherId,
        $academicYearId,
        $termId,
        $classId,
        $subjectId

    ]);


    $submissionId =
        (int)$conn->lastInsertId();


    $submission = [

        "id" =>
            $submissionId,

        "status" =>
            "Draft",

        "review_comment" =>
            ""

    ];
}


/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | RELOAD STATUS
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT *
            FROM mark_submissions
            WHERE id = ?
            AND teacher_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $submission["id"],
            $teacherId
        ]);

        $submission =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$submission) {

            throw new Exception(
                "Submission record was not found."
            );
        }


        if (
            $submission["status"] === "Submitted" ||
            $submission["status"] === "Approved"
        ) {

            throw new Exception(
                "This submission is already locked."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | COUNT STUDENTS
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM students
            WHERE class_id = ?
        ");

        $stmt->execute([
            $classId
        ]);

        $studentCount =
            (int)$stmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | COUNT MARKS
        |--------------------------------------------------------------------------
        */

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

            $classId,
            $subjectId,
            $termId

        ]);

        $markCount =
            (int)$stmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | REQUIRE ALL STUDENTS
        |--------------------------------------------------------------------------
        */

        if (
            $studentCount === 0
        ) {

            throw new Exception(
                "There are no students in this class."
            );
        }


        if (
            $markCount < $studentCount
        ) {

            $missing =
                $studentCount -
                $markCount;

            throw new Exception(

                "Submission cannot be completed. "
                . $missing
                . " student(s) still have no marks."

            );
        }


        /*
        |--------------------------------------------------------------------------
        | SUBMIT
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            UPDATE mark_submissions

            SET

                status = 'Submitted',

                submitted_at = NOW(),

                updated_at = NOW()

            WHERE id = ?

            AND teacher_id = ?
        ");

        $stmt->execute([

            $submission["id"],
            $teacherId

        ]);


        $success =
            "Marks submitted successfully. They are now locked pending administrative review.";


        /*
        |--------------------------------------------------------------------------
        | REFRESH
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT *
            FROM mark_submissions
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $submission["id"]
        ]);

        $submission =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


    } catch (
        Throwable $e
    ) {

        $error =
            $e->getMessage();
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
    HIBS | Submit Marks
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

.container {

    max-width: 720px;

    margin: 70px auto;

    padding: 20px;

}

.card {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}

.header {

    padding: 22px;

    background: #263238;

    color: #ffffff;

}

.header-title {

    font-size: 16px;

    font-weight: 700;

    letter-spacing: .8px;

}

.header-subtitle {

    margin-top: 5px;

    color: #b9c3c8;

    font-size: 8px;

}

.body {

    padding: 25px;

}

h1 {

    margin: 0;

    font-size: 21px;

    font-weight: 600;

}

.description {

    margin-top: 7px;

    color: #7a858a;

    font-size: 9px;

    line-height: 1.7;

}

.details {

    margin-top: 20px;

    border:
        1px solid
        #deddd8;

}

.row {

    display: grid;

    grid-template-columns: 180px 1fr;

    border-bottom:
        1px solid
        #e7e5e1;

}

.row:last-child {
    border-bottom: 0;
}

.label {

    padding: 12px;

    background: #f2f3f1;

    color: #69767b;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

}

.value {

    padding: 12px;

    color: #455a64;

    font-size: 9px;

}

.status {

    display: inline-block;

    padding: 7px 10px;

    background: #eef0ef;

    color: #586a70;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}

.notice {

    margin-top: 20px;

    padding: 15px;

    background: #f1f5f6;

    border-left:
        3px solid
        #607d8b;

    color: #586a70;

    font-size: 9px;

    line-height: 1.7;

}

.success {

    margin-bottom: 18px;

    padding: 13px;

    background: #eaf3ed;

    border:
        1px solid
        #c9dccd;

    color: #416b4f;

    font-size: 9px;

}

.error {

    margin-bottom: 18px;

    padding: 13px;

    background: #fbefef;

    border:
        1px solid
        #e0c8c8;

    color: #8b4b4b;

    font-size: 9px;

}

.actions {

    margin-top: 22px;

    display: flex;

    justify-content: flex-end;

    gap: 8px;

}

.btn {

    padding:
        10px 15px;

    border-radius: 3px;

    font-family: inherit;

    font-size: 8px;

    font-weight: bold;

    text-decoration: none;

    cursor: pointer;

}

.back {

    background: #ffffff;

    border:
        1px solid
        #d3d2ce;

    color: #65747a;

}

.submit {

    border: 0;

    background: #455a64;

    color: #ffffff;

}

.submit:hover {

    background: #263238;

}

.locked {

    padding: 12px;

    margin-top: 20px;

    background: #edf3ee;

    color: #496b55;

    font-size: 9px;

}

@media(max-width:600px) {

    .container {

        margin: 20px auto;

        padding: 12px;

    }

    .row {

        grid-template-columns: 1fr;

    }

    .actions {

        flex-direction: column;

    }

    .btn {

        width: 100%;

        text-align: center;

    }

}

</style>

</head>


<body>


<div class="container">


<div class="card">


<div class="header">

    <div class="header-title">
        HIBS REPORTS
    </div>

    <div class="header-subtitle">
        HILLTOP INTERNATIONAL BRITISH SCHOOL
    </div>

</div>


<div class="body">


<?php if (
    $success
): ?>

    <div class="success">

        <?= h(
            $success
        ) ?>

    </div>

<?php endif; ?>


<?php if (
    $error
): ?>

    <div class="error">

        <?= h(
            $error
        ) ?>

    </div>

<?php endif; ?>


<h1>
    Submit Marks for Review
</h1>


<div class="description">

    Review the information below before submitting.
    Once submitted, the marks are locked and cannot
    be changed until an administrator reviews them.

</div>


<div class="details">


    <div class="row">

        <div class="label">
            Academic Year
        </div>

        <div class="value">
            <?= h(
                $academicYear
            ) ?>
        </div>

    </div>


    <div class="row">

        <div class="label">
            Term
        </div>

        <div class="value">
            <?= h(
                $termName
            ) ?>
        </div>

    </div>


    <div class="row">

        <div class="label">
            Class
        </div>

        <div class="value">
            <?= h(
                $className
            ) ?>
        </div>

    </div>


    <div class="row">

        <div class="label">
            Subject
        </div>

        <div class="value">
            <?= h(
                $subjectName
            ) ?>
        </div>

    </div>


    <div class="row">

        <div class="label">
            Current Status
        </div>

        <div class="value">

            <span class="status">

                <?= h(
                    $submission["status"]
                ) ?>

            </span>

        </div>

    </div>


</div>


<?php if (
    $submission["status"] === "Submitted"
    ||
    $submission["status"] === "Approved"
): ?>


    <div class="locked">

        🔒 This marks submission is locked.

        <br><br>

        Status:

        <strong>
            <?= h(
                $submission["status"]
            ) ?>
        </strong>

    </div>


    <div class="actions">

        <a
            href="marks.php"
            class="btn back"
        >
            Return to Marks
        </a>

    </div>


<?php else: ?>


    <div class="notice">

        Before submitting, make sure:

        <br><br>

        ✓ All students have marks

        <br>

        ✓ The marks are correct

        <br>

        ✓ The subject and class are correct

        <br>

        ✓ The selected term is correct

        <br><br>

        After submission, the marks will be locked
        for administrative review.

    </div>


    <form
        method="POST"
        class="actions"
    >

        <a
            href="marks.php"
            class="btn back"
        >
            Return to Marks
        </a>


        <button
            type="submit"
            class="btn submit"
            onclick="
                return confirm(
                    'Submit these marks for administrative review? The marks will be locked.'
                );
            "
        >

            Submit for Review

        </button>

    </form>


<?php endif; ?>


</div>

</div>

</div>


</body>

</html>
