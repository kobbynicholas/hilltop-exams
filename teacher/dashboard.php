<?php

session_start();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| TEACHER AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION["role"] !== "teacher") {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| GET TEACHER INFORMATION
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
    WHERE t.user_id = ?
    LIMIT 1
");

$stmt->execute([$user_id]);

$teacher = $stmt->fetch();

if (!$teacher) {
    die("Teacher profile could not be found.");
}

$teacher_id = (int) $teacher["id"];

/*
|--------------------------------------------------------------------------
| COUNT ASSIGNED CLASSES
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM teacher_classes
    WHERE teacher_id = ?
");

$stmt->execute([$teacher_id]);

$classCount = $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| COUNT ASSIGNED SUBJECTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM teacher_subjects
    WHERE teacher_id = ?
");

$stmt->execute([$teacher_id]);

$subjectCount = $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| COUNT STUDENTS IN ASSIGNED CLASSES
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    INNER JOIN teacher_classes tc
        ON tc.class_id = s.class_id
    WHERE tc.teacher_id = ?
");

$stmt->execute([$teacher_id]);

$studentCount = $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| GET ASSIGNED CLASSES
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>HIBS Reports | Teacher Portal</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

    <style>

        .teacher-welcome {
            background: #641c2b;
            color: white;
            padding: 32px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .teacher-welcome::after {
            content: "HIBS";
            position: absolute;
            right: 35px;
            top: 15px;
            font-size: 80px;
            font-weight: bold;
            opacity: .06;
        }

        .teacher-welcome h2 {
            font-size: 29px;
            font-weight: normal;
            margin-bottom: 8px;
        }

        .teacher-welcome p {
            font-family: Arial, sans-serif;
            font-size: 13px;
            opacity: .85;
        }

        .teacher-stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .teacher-stat {
            background: white;
            border: 1px solid #e5dfd7;
            padding: 24px;
        }

        .teacher-stat span {
            display: block;
            color: #77706d;
            font-family: Arial, sans-serif;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .teacher-stat strong {
            display: block;
            color: #641c2b;
            font-size: 32px;
            font-weight: normal;
            margin-top: 8px;
        }

        .class-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .class-card {
            background: white;
            border: 1px solid #e5dfd7;
            padding: 24px;
            transition: .2s;
        }

        .class-card:hover {
            border-color: #b58a3a;
        }

        .class-card h3 {
            color: #641c2b;
            font-size: 21px;
            font-weight: normal;
        }

        .class-card p {
            font-family: Arial, sans-serif;
            color: #77706d;
            font-size: 12px;
            margin-top: 6px;
        }

        .class-card .student-number {
            margin: 20px 0;
            color: #35131b;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }

        @media(max-width: 900px) {

            .class-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media(max-width: 650px) {

            .teacher-stat-grid,
            .class-grid {
                grid-template-columns: 1fr;
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
                <?= htmlspecialchars($teacher["full_name"]) ?>
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

    <a href="dashboard.php"
       class="active">
        Dashboard
    </a>

    <a href="classes.php">
        My Classes
    </a>

    <a href="subjects.php">
        My Subjects
    </a>

     <a href="marks.php">
        Marks
    </a>

    <a href="profile.php">
        My Profile
    </a>

</nav>


    
<main class="page">

    <div class="teacher-welcome">

        <h2>
            Welcome,
            <?= htmlspecialchars($teacher["full_name"]) ?>
        </h2>

        <p>
            Teacher Portal ·
            <?= htmlspecialchars($teacher["employee_id"]) ?>
        </p>

    </div>


    <div class="teacher-stat-grid">

        <div class="teacher-stat">

            <span>
                My Classes
            </span>

            <strong>
                <?= $classCount ?>
            </strong>

        </div>

        <div class="teacher-stat">

            <span>
                My Subjects
            </span>

            <strong>
                <?= $subjectCount ?>
            </strong>

        </div>

        <div class="teacher-stat">

            <span>
                My Students
            </span>

            <strong>
                <?= $studentCount ?>
            </strong>

        </div>

    </div>


    <div class="content-panel">

        <h3>
            My Classes
        </h3>

        <?php if (!$classes): ?>

            <p style="font-family:Arial;color:#777;">
                No classes have been assigned to you yet.
                Please contact the administrator.
            </p>

        <?php else: ?>

            <div class="class-grid">

                <?php foreach ($classes as $class): ?>

                    <div class="class-card">

                        <h3>
                            <?= htmlspecialchars(
                                $class["class_name"]
                            ) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars(
                                $class["class_level"] ?: "Academic Class"
                            ) ?>
                        </p>

                        <div class="student-number">

                            <?= (int)$class["student_count"] ?>

                            student(s)

                        </div>

                        <a
                            href="classes.php?class_id=<?= $class["id"] ?>"
                            class="btn btn-primary"
                        >
                            View Class
                        </a>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</main>

</body>

</html>
