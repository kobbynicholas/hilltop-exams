<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "teacher") {

    header("Location: ../login.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id
    FROM teachers
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([
    (int)$_SESSION["user_id"]
]);

$teacher = $stmt->fetch();

if (!$teacher) {
    die("Teacher profile not found.");
}

$teacher_id = (int)$teacher["id"];

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

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | My Subjects</title>

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

    <a href="classes.php">
        My Classes
    </a>

    <a href="subjects.php"
       class="active">
        My Subjects
    </a>

    <a href="profile.php">
        My Profile
    </a>

</nav>


<main class="page">

    <div class="page-heading">

        <div>

            <h2>
                My Subjects
            </h2>

            <p>
                Subjects assigned to you by the administrator.
            </p>

        </div>

    </div>


    <div class="content-panel">

        <table class="hibs-table">

            <thead>

            <tr>

                <th>#</th>
                <th>Subject Code</th>
                <th>Subject</th>
                <th>Action</th>

            </tr>

            </thead>

            <tbody>

            <?php $number = 1; ?>

            <?php foreach ($subjects as $subject): ?>

                <tr>

                    <td>
                        <?= $number++ ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $subject["subject_code"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $subject["subject_name"]
                        ) ?>
                    </td>

                    <td>

                        <a
                            href="classes.php"
                            class="btn btn-primary"
                        >
                            View Classes
                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (!$subjects): ?>

                <tr>

                    <td colspan="4">
                        No subjects have been assigned to you.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>

</html>
