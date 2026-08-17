<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$teacher_id = (int)($_GET["id"] ?? 0);

if ($teacher_id <= 0) {
    header("Location: teachers.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET TEACHER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        t.id,
        t.employee_id,
        t.phone,
        t.qualification,
        t.specialization,
        u.full_name,
        u.username
    FROM teachers t
    INNER JOIN users u
        ON u.id = t.user_id
    WHERE t.id = ?
");

$stmt->execute([$teacher_id]);

$teacher = $stmt->fetch();

if (!$teacher) {
    header("Location: teachers.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| SAVE ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subjects = $_POST["subjects"] ?? [];
    $classes  = $_POST["classes"] ?? [];

    try {

        $conn->beginTransaction();

        /*
        |--------------------------------------------------------------
        | Remove previous subject assignments
        |--------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            DELETE FROM teacher_subjects
            WHERE teacher_id = ?
        ");

        $stmt->execute([$teacher_id]);

        /*
        |--------------------------------------------------------------
        | Add selected subjects
        |--------------------------------------------------------------
        */

        if (!empty($subjects)) {

            $stmt = $conn->prepare("
                INSERT INTO teacher_subjects
                (teacher_id, subject_id)
                VALUES (?, ?)
            ");

            foreach ($subjects as $subject_id) {

                $stmt->execute([
                    $teacher_id,
                    (int)$subject_id
                ]);
            }
        }

        /*
        |--------------------------------------------------------------
        | Remove previous class assignments
        |--------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            DELETE FROM teacher_classes
            WHERE teacher_id = ?
        ");

        $stmt->execute([$teacher_id]);

        /*
        |--------------------------------------------------------------
        | Add selected classes
        |--------------------------------------------------------------
        */

        if (!empty($classes)) {

            $stmt = $conn->prepare("
                INSERT INTO teacher_classes
                (teacher_id, class_id)
                VALUES (?, ?)
            ");

            foreach ($classes as $class_id) {

                $stmt->execute([
                    $teacher_id,
                    (int)$class_id
                ]);
            }
        }

        $conn->commit();

        $message = "Teacher assignments have been saved successfully.";

    } catch (Exception $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $message = "Unable to save assignments: " . $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| GET SUBJECTS
|--------------------------------------------------------------------------
*/

$subjects = $conn->query("
    SELECT id, subject_code, subject_name
    FROM subjects
    ORDER BY subject_name
")->fetchAll();

/*
|--------------------------------------------------------------------------
| GET CLASSES
|--------------------------------------------------------------------------
*/

$classes = $conn->query("
    SELECT id, class_name, class_level
    FROM classes
    ORDER BY class_name
")->fetchAll();

/*
|--------------------------------------------------------------------------
| GET CURRENT SUBJECT ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT subject_id
    FROM teacher_subjects
    WHERE teacher_id = ?
");

$stmt->execute([$teacher_id]);

$assignedSubjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

/*
|--------------------------------------------------------------------------
| GET CURRENT CLASS ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT class_id
    FROM teacher_classes
    WHERE teacher_id = ?
");

$stmt->execute([$teacher_id]);

$assignedClasses = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        HIBS Reports | Teacher Assignment
    </title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

    <style>

        .teacher-profile {
            background: white;
            border: 1px solid #e5dfd7;
            padding: 28px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .teacher-profile h3 {
            color: #641c2b;
            font-size: 24px;
            font-weight: normal;
            margin-bottom: 8px;
        }

        .teacher-profile p {
            font-family: Arial, sans-serif;
            color: #77706d;
            font-size: 13px;
            line-height: 1.8;
        }

        .assignment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .assignment-panel {
            background: white;
            border: 1px solid #e5dfd7;
            padding: 28px;
        }

        .assignment-panel h3 {
            color: #641c2b;
            font-size: 20px;
            font-weight: normal;
            margin-bottom: 5px;
        }

        .assignment-panel > p {
            font-family: Arial, sans-serif;
            color: #77706d;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .assignment-item {
            display: flex;
            align-items: center;
            gap: 12px;
            border-top: 1px solid #eee8df;
            padding: 14px 0;
            font-family: Arial, sans-serif;
        }

        .assignment-item input {
            width: 18px;
            height: 18px;
            accent-color: #641c2b;
        }

        .assignment-item label {
            cursor: pointer;
            flex: 1;
        }

        .assignment-code {
            color: #b58a3a;
            font-size: 11px;
            font-weight: bold;
        }

        .save-area {
            background: white;
            border: 1px solid #e5dfd7;
            padding: 20px;
            margin-top: 25px;
            text-align: right;
        }

        @media(max-width: 800px) {

            .assignment-grid {
                grid-template-columns: 1fr;
            }

            .teacher-profile {
                display: block;
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

            <small>Administrator</small>

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

    <a href="students.php">
        Students
    </a>

    <a href="classes.php">
        Classes
    </a>

    <a href="subjects.php">
        Subjects
    </a>

    <a href="teachers.php"
       class="active">
        Teachers
    </a>

    <a href="academic_years.php">
        Academic Years
    </a>

    <a href="terms.php">
        Terms
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


<main class="page">

    <div class="page-heading">

        <div>

            <h2>
                Teacher Assignments
            </h2>

            <p>
                Assign academic subjects and classes to this teacher.
            </p>

        </div>

        <a href="teachers.php"
           class="btn btn-light">
            ← Back to Teachers
        </a>

    </div>


    <?php if ($message): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <div class="teacher-profile">

        <div>

            <h3>
                <?= htmlspecialchars(
                    $teacher["full_name"]
                ) ?>
            </h3>

            <p>

                <strong>Employee ID:</strong>
                <?= htmlspecialchars(
                    $teacher["employee_id"]
                ) ?>

                <br>

                <strong>Username:</strong>
                <?= htmlspecialchars(
                    $teacher["username"]
                ) ?>

                <br>

                <strong>Specialization:</strong>
                <?= htmlspecialchars(
                    $teacher["specialization"] ?: "Not specified"
                ) ?>

            </p>

        </div>

        <div>

            <span class="btn btn-light">
                Teacher
            </span>

        </div>

    </div>


    <form method="POST">


        <div class="assignment-grid">


            <!-- SUBJECTS -->

            <div class="assignment-panel">

                <h3>
                    Subjects
                </h3>

                <p>
                    Select the subjects this teacher is responsible for.
                </p>


                <?php if (!$subjects): ?>

                    <p>
                        No subjects have been created yet.
                    </p>

                <?php endif; ?>


                <?php foreach ($subjects as $subject): ?>

                    <div class="assignment-item">

                        <input
                            type="checkbox"
                            name="subjects[]"
                            value="<?= $subject["id"] ?>"
                            id="subject_<?= $subject["id"] ?>"
                            <?= in_array(
                                $subject["id"],
                                $assignedSubjects
                            ) ? "checked" : "" ?>
                        >

                        <label
                            for="subject_<?= $subject["id"] ?>"
                        >

                            <?= htmlspecialchars(
                                $subject["subject_name"]
                            ) ?>

                            <span class="assignment-code">

                                (
                                <?= htmlspecialchars(
                                    $subject["subject_code"]
                                ) ?>
                                )

                            </span>

                        </label>

                    </div>

                <?php endforeach; ?>

            </div>


            <!-- CLASSES -->

            <div class="assignment-panel">

                <h3>
                    Classes
                </h3>

                <p>
                    Select the classes this teacher will teach.
                </p>


                <?php if (!$classes): ?>

                    <p>
                        No classes have been created yet.
                    </p>

                <?php endif; ?>


                <?php foreach ($classes as $class): ?>

                    <div class="assignment-item">

                        <input
                            type="checkbox"
                            name="classes[]"
                            value="<?= $class["id"] ?>"
                            id="class_<?= $class["id"] ?>"
                            <?= in_array(
                                $class["id"],
                                $assignedClasses
                            ) ? "checked" : "" ?>
                        >

                        <label
                            for="class_<?= $class["id"] ?>"
                        >

                            <?= htmlspecialchars(
                                $class["class_name"]
                            ) ?>

                            <?php if ($class["class_level"]): ?>

                                <span class="assignment-code">

                                    —
                                    <?= htmlspecialchars(
                                        $class["class_level"]
                                    ) ?>

                                </span>

                            <?php endif; ?>

                        </label>

                    </div>

                <?php endforeach; ?>

            </div>


        </div>


        <div class="save-area">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Teacher Assignments
            </button>

        </div>


    </form>

</main>

</body>

</html>
