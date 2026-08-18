<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "teacher") {

    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

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

$teacher_id = (int) $teacher["id"];

/*
|--------------------------------------------------------------------------
| SELECT CLASS
|--------------------------------------------------------------------------
*/

$class_id = (int)($_GET["class_id"] ?? 0);

/*
|--------------------------------------------------------------------------
| VERIFY TEACHER HAS ACCESS TO CLASS
|--------------------------------------------------------------------------
*/

if ($class_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            c.id,
            c.class_name,
            c.class_level
        FROM teacher_classes tc
        INNER JOIN classes c
            ON c.id = tc.class_id
        WHERE
            tc.teacher_id = ?
            AND tc.class_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $teacher_id,
        $class_id
    ]);

    $selectedClass = $stmt->fetch();

    if (!$selectedClass) {
        die("You do not have access to this class.");
    }

    /*
    |--------------------------------------------------------------------------
    | GET STUDENTS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            student_id,
            first_name,
            middle_name,
            last_name,
            gender,
            status
        FROM students
        WHERE class_id = ?
        ORDER BY last_name, first_name
    ");

    $stmt->execute([$class_id]);

    $students = $stmt->fetchAll();

} else {

    /*
    |--------------------------------------------------------------------------
    | GET ALL ASSIGNED CLASSES
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            c.id,
            c.class_name,
            c.class_level,
            COUNT(s.id) AS student_count
        FROM teacher_classes tc

        INNER JOIN classes c
            ON c.id = tc.class_id

        LEFT JOIN students s
            ON s.class_id = c.id
            AND s.status = 'Active'

        WHERE tc.teacher_id = ?

        GROUP BY
            c.id,
            c.class_name,
            c.class_level

        ORDER BY c.class_name
    ");

    $stmt->execute([$teacher_id]);

    $classes = $stmt->fetchAll();
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | My Classes</title>

<link rel="stylesheet"
      href="../assets/css/style.css">

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

        <strong>
            <?= htmlspecialchars($_SESSION["full_name"]) ?>
        </strong>

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

    <a href="classes.php"
       class="active">
        My Classes
    </a>

    <a href="subjects.php">
        My Subjects
    </a>

    <a href="profile.php">
        My Profile
    </a>

</nav>


<main class="page">

<?php if ($class_id > 0): ?>

    <div class="page-heading">

        <div>

            <h2>
                <?= htmlspecialchars(
                    $selectedClass["class_name"]
                ) ?>
            </h2>

            <p>
                <?= htmlspecialchars(
                    $selectedClass["class_level"] ?: "Class"
                ) ?>
            </p>

        </div>

        <a
            href="classes.php"
            class="btn btn-light"
        >
            ← My Classes
        </a>

    </div>


    <div class="content-panel">

        <h3>
            Students
        </h3>

        <div class="table-wrapper">

            <table class="hibs-table">

                <thead>

                <tr>

                    <th>#</th>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Gender</th>
                    <th>Status</th>

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
                            <?= htmlspecialchars(
                                $student["student_id"]
                            ) ?>
                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $student["first_name"] . " " .
                                ($student["middle_name"]
                                    ? $student["middle_name"] . " "
                                    : "") .
                                $student["last_name"]
                            ) ?>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $student["gender"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $student["status"]
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$students): ?>

                    <tr>

                        <td colspan="5">
                            No students are currently assigned to this class.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


<?php else: ?>


    <div class="page-heading">

        <div>

            <h2>
                My Classes
            </h2>

            <p>
                Classes assigned to you by the administrator.
            </p>

        </div>

    </div>


    <div class="content-panel">

        <div class="table-wrapper">

            <table class="hibs-table">

                <thead>

                <tr>

                    <th>Class</th>
                    <th>Level</th>
                    <th>Students</th>
                    <th>Action</th>

                </tr>

                </thead>

                <tbody>

                <?php foreach ($classes as $class): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $class["class_name"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $class["class_level"] ?: "-"
                            ) ?>
                        </td>

                        <td>
                            <?= (int)$class["student_count"] ?>
                        </td>

                        <td>

                            <a
                                href="classes.php?class_id=<?= $class["id"] ?>"
                                class="btn btn-primary"
                            >
                                View Students
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$classes): ?>

                    <tr>

                        <td colspan="4">
                            No classes have been assigned to you.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


<?php endif; ?>

</main>

</body>

</html>
