<?php

session_start();

require_once "../config/db.php";

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "teacher"
) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| GET TEACHER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id
    FROM teachers
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$user_id]);

$teacher = $stmt->fetch();

if (!$teacher) {
    die("Teacher profile not found.");
}

$teacher_id = (int)$teacher["id"];


/*
|--------------------------------------------------------------------------
| GET ACTIVE TERM
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT
        t.id,
        t.term_name,
        ay.academic_year
    FROM terms t

    INNER JOIN academic_years ay
        ON ay.id = t.academic_year_id

    WHERE
        t.status = 'Active'
        AND ay.status = 'Active'

    ORDER BY t.id DESC

    LIMIT 1
");

$activeTerm = $stmt->fetch();

if (!$activeTerm) {

    die(
        "There is no active academic year and term. "
        . "Please contact the administrator."
    );
}

$term_id = (int)$activeTerm["id"];


/*
|--------------------------------------------------------------------------
| SELECT CLASS / SUBJECT
|--------------------------------------------------------------------------
*/

$class_id = (int)($_GET["class_id"] ?? 0);
$subject_id = (int)($_GET["subject_id"] ?? 0);


/*
|--------------------------------------------------------------------------
| VERIFY CLASS
|--------------------------------------------------------------------------
*/

$allowedClass = false;

if ($class_id > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM teacher_classes
        WHERE
            teacher_id = ?
            AND class_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $teacher_id,
        $class_id
    ]);

    $allowedClass = (bool)$stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| VERIFY SUBJECT
|--------------------------------------------------------------------------
*/

$allowedSubject = false;

if ($subject_id > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM teacher_subjects
        WHERE
            teacher_id = ?
            AND subject_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $teacher_id,
        $subject_id
    ]);

    $allowedSubject = (bool)$stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| VERIFY CLASS-SUBJECT COMBINATION
|--------------------------------------------------------------------------
*/

$validCombination = false;

if (
    $class_id > 0 &&
    $subject_id > 0 &&
    $allowedClass &&
    $allowedSubject
) {

    $stmt = $conn->prepare("
        SELECT id
        FROM class_subjects
        WHERE
            class_id = ?
            AND subject_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $class_id,
        $subject_id
    ]);

    $validCombination = (bool)$stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| GET TEACHER CLASSES
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        c.id,
        c.class_name
    FROM teacher_classes tc

    INNER JOIN classes c
        ON c.id = tc.class_id

    WHERE tc.teacher_id = ?

    ORDER BY c.class_name
");

$stmt->execute([$teacher_id]);

$classes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| GET TEACHER SUBJECTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.subject_code,
        s.subject_name
    FROM teacher_subjects ts

    INNER JOIN subjects s
        ON s.id = ts.subject_id

    WHERE ts.teacher_id = ?

    ORDER BY s.subject_name
");

$stmt->execute([$teacher_id]);

$subjects = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| GET STUDENTS
|--------------------------------------------------------------------------
*/

$students = [];

if ($validCombination) {

    $stmt = $conn->prepare("
        SELECT
            id,
            student_id,
            first_name,
            middle_name,
            last_name
        FROM students
        WHERE
            class_id = ?
            AND status = 'Active'
        ORDER BY
            last_name,
            first_name
    ");

    $stmt->execute([$class_id]);

    $students = $stmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| GET ASSESSMENT COMPONENTS
|--------------------------------------------------------------------------
*/

$components = [];

if ($validCombination) {

    $stmt = $conn->prepare("
        SELECT
            ac.id,
            ac.component_name,
            ac.max_score,
            ac.weight
        FROM subject_assessments sa

        INNER JOIN assessment_components ac
            ON ac.id = sa.component_id

        WHERE
            sa.class_id = ?
            AND sa.subject_id = ?
            AND ac.status = 'Active'

        ORDER BY sa.id
    ");

    $stmt->execute([
        $class_id,
        $subject_id
    ]);

    $components = $stmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| SAVE MARKS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $validCombination
) {

    $scores = $_POST["score"] ?? [];

    try {

        $conn->beginTransaction();

        foreach ($students as $student) {

            $student_id = (int)$student["id"];

            foreach ($components as $component) {

                $component_id = (int)$component["id"];

                $value = null;

                if (
                    isset(
                        $scores[$student_id][$component_id]
                    ) &&
                    $scores[$student_id][$component_id] !== ""
                ) {

                    $value = (float)
                        $scores[$student_id][$component_id];

                    if (
                        $value < 0 ||
                        $value > (float)$component["max_score"]
                    ) {

                        throw new Exception(
                            "A score is outside the allowed range."
                        );
                    }
                }

                $stmt = $conn->prepare("
                    INSERT INTO mark_entries
                    (
                        student_id,
                        class_id,
                        subject_id,
                        term_id,
                        component_id,
                        score
                    )
                    VALUES (?, ?, ?, ?, ?, ?)

                    ON DUPLICATE KEY UPDATE
                        score = VALUES(score)
                ");

                $stmt->execute([
                    $student_id,
                    $class_id,
                    $subject_id,
                    $term_id,
                    $component_id,
                    $value
                ]);
            }
        }

        $conn->commit();

        header(
            "Location: marks.php?class_id="
            . $class_id
            . "&subject_id="
            . $subject_id
            . "&saved=1"
        );

        exit;

    } catch (Exception $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| GET EXISTING MARKS
|--------------------------------------------------------------------------
*/

$existingMarks = [];

if ($validCombination) {

    $stmt = $conn->prepare("
        SELECT
            student_id,
            component_id,
            score
        FROM mark_entries
        WHERE
            class_id = ?
            AND subject_id = ?
            AND term_id = ?
    ");

    $stmt->execute([
        $class_id,
        $subject_id,
        $term_id
    ]);

    foreach ($stmt->fetchAll() as $mark) {

        $existingMarks[
            $mark["student_id"]
        ][
            $mark["component_id"]
        ] = $mark["score"];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | Marks Entry</title>

<link rel="stylesheet"
      href="../assets/css/style.css">

<style>

.marks-header {
    background: #641c2b;
    color: white;
    padding: 24px;
    margin-bottom: 25px;
}

.marks-header h2 {
    font-weight: normal;
    font-size: 25px;
}

.marks-header p {
    font-family: Arial, sans-serif;
    font-size: 12px;
    margin-top: 6px;
    opacity: .85;
}

.selection-panel {
    background: white;
    border: 1px solid #e5dfd7;
    padding: 25px;
    margin-bottom: 25px;
}

.marks-table input {
    width: 85px;
    padding: 9px;
    border: 1px solid #dcd5cb;
    text-align: center;
}

.marks-table input:focus {
    border-color: #b58a3a;
    outline: none;
}

.component-heading {
    text-align: center !important;
}

.student-name {
    min-width: 220px;
}

.save-bar {
    background: white;
    border: 1px solid #e5dfd7;
    padding: 20px;
    text-align: right;
    position: sticky;
    bottom: 0;
}

@media(max-width:900px) {

    .marks-table {
        min-width: 900px;
    }

}

</style>

</head>

<body>

<header class="hibs-header">

    <div class="brand">

        <div class="brand-mark">H</div>

        <div class="brand-text">

            <h1>HIBS REPORTS</h1>

            <span>
                HILLTOP INTERNATIONAL BRITISH SCHOOL
            </span>

        </div>

    </div>

    <div class="top-user">

        <div class="user-name">

            <strong>
                <?= htmlspecialchars($_SESSION["full_name"]) ?>
            </strong>

            <small>Teacher</small>

        </div>

        <a href="../logout.php"
           class="logout-link">
            Sign out
        </a>

    </div>

</header>


<nav class="hibs-nav">

    <a href="dashboard.php">
        Dashboard
    </a>

    <a href="classes.php">
        My Classes
    </a>

    <a href="subjects.php">
        My Subjects
    </a>

    <a href="marks.php"
       class="active">
        Marks
    </a>

    <a href="profile.php">
        My Profile
    </a>

</nav>


<main class="page">

    <div class="marks-header">

        <h2>
            Marks Entry
        </h2>

        <p>

            <?= htmlspecialchars(
                $activeTerm["academic_year"]
            ) ?>

            ·

            <?= htmlspecialchars(
                $activeTerm["term_name"]
            ) ?>

        </p>

    </div>


    <?php if (isset($_GET["saved"])): ?>

        <div class="alert alert-success">

            Marks saved successfully.

        </div>

    <?php endif; ?>


    <?php if (isset($error)): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <div class="selection-panel">

        <form method="GET">

            <div class="form-grid">

                <div class="form-group">

                    <label>Class</label>

                    <select
                        name="class_id"
                        required
                    >

                        <option value="">
                            Select Class
                        </option>

                        <?php foreach ($classes as $class): ?>

                            <option
                                value="<?= $class["id"] ?>"
                                <?= $class_id === (int)$class["id"]
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= htmlspecialchars(
                                    $class["class_name"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>Subject</label>

                    <select
                        name="subject_id"
                        required
                    >

                        <option value="">
                            Select Subject
                        </option>

                        <?php foreach ($subjects as $subject): ?>

                            <option
                                value="<?= $subject["id"] ?>"
                                <?= $subject_id === (int)$subject["id"]
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= htmlspecialchars(
                                    $subject["subject_name"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Load Mark Sheet
            </button>

        </form>

    </div>


    <?php if ($class_id && $subject_id && !$validCombination): ?>

        <div class="alert alert-danger">

            You are not authorised to enter marks for this
            class and subject combination.

        </div>

    <?php endif; ?>


    <?php if ($validCombination): ?>


        <div class="content-panel">

            <h3>
                Student Mark Sheet
            </h3>

            <p style="
                font-family:Arial;
                color:#777;
                font-size:13px;
                margin-bottom:20px;
            ">

                Enter scores according to the maximum scores
                shown in the column headings.

            </p>


            <?php if (!$components): ?>

                <div class="alert alert-danger">

                    No assessment components have been configured
                    for this class and subject.

                    Please contact the administrator.

                </div>

            <?php elseif (!$students): ?>

                <div class="alert alert-danger">

                    There are no active students in this class.

                </div>

            <?php else: ?>


                <form method="POST">


                    <div class="table-wrapper">

                        <table
                            class="hibs-table marks-table"
                        >

                            <thead>

                            <tr>

                                <th>#</th>

                                <th class="student-name">
                                    Student
                                </th>

                                <?php foreach ($components as $component): ?>

                                    <th class="component-heading">

                                        <?= htmlspecialchars(
                                            $component["component_name"]
                                        ) ?>

                                        <br>

                                        <small>
                                            / <?= $component["max_score"] ?>
                                            · <?= $component["weight"] ?>%
                                        </small>

                                    </th>

                                <?php endforeach; ?>

                            </tr>

                            </thead>


                            <tbody>

                            <?php $number = 1; ?>

                            <?php foreach ($students as $student): ?>

                                <tr>

                                    <td>
                                        <?= $number++ ?>
                                    </td>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $student["last_name"]
                                            ) ?>,

                                            <?= htmlspecialchars(
                                                $student["first_name"]
                                            ) ?>

                                            <?php if (
                                                $student["middle_name"]
                                            ): ?>

                                                <?= htmlspecialchars(
                                                    $student["middle_name"]
                                                ) ?>

                                            <?php endif; ?>

                                        </strong>

                                        <br>

                                        <small>

                                            <?= htmlspecialchars(
                                                $student["student_id"]
                                            ) ?>

                                        </small>

                                    </td>


                                    <?php foreach (
                                        $components
                                        as $component
                                    ): ?>

                                        <td>

                                            <input
                                                type="number"
                                                name="score[<?= $student["id"] ?>][<?= $component["id"] ?>]"
                                                min="0"
                                                max="<?= $component["max_score"] ?>"
                                                step="0.01"
                                                value="<?= isset(
                                                    $existingMarks[
                                                        $student["id"]
                                                    ][
                                                        $component["id"]
                                                    ]
                                                )
                                                    ? htmlspecialchars(
                                                        $existingMarks[
                                                            $student["id"]
                                                        ][
                                                            $component["id"]
                                                        ]
                                                    )
                                                    : "" ?>"
                                            >

                                        </td>

                                    <?php endforeach; ?>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <div class="save-bar">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Save Marks
                        </button>

                    </div>


                </form>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</main>

</body>

</html>
