<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$students = $conn
    ->query("SELECT COUNT(*) FROM students")
    ->fetchColumn();

$teachers = $conn
    ->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")
    ->fetchColumn();

$classes = $conn
    ->query("SELECT COUNT(*) FROM classes")
    ->fetchColumn();

$subjects = $conn
    ->query("SELECT COUNT(*) FROM subjects")
    ->fetchColumn();

$recentStudents = $conn->query("
    SELECT
        s.*,
        c.class_name
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    ORDER BY s.id DESC
    LIMIT 5
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>HIBS Reports | Dashboard</title>

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

        <div class="user-name">

            <strong>
                <?= htmlspecialchars($_SESSION["full_name"]) ?>
            </strong>

            <small>Administrator</small>

        </div>

        <a href="../logout.php" class="logout-link">
            Sign out
        </a>

    </div>

</header>

<nav class="hibs-nav">

    <nav class="hibs-nav">

    <a href="dashboard.php">Dashboard</a>

    <a href="students.php">Students</a>

    <a href="classes.php">Classes</a>

    <a href="subjects.php">Subjects</a>

    <a href="teachers.php">Teachers</a>

    <a href="academic_years.php">Academic Years</a>

    <a href="terms.php">Terms</a>

    <a href="marks.php">Marks</a>

    <a href="attendance.php">Attendance</a>

    <a href="reports.php">Reports</a>

    <a href="settings.php">Settings</a>

</nav>

</nav>

<main class="page">

    <div class="overview-title">

        <h2>Academic Overview</h2>

        <p>
            Administration and academic records
        </p>

    </div>

    <div class="stat-grid">

        <div class="stat-card">

            <small>Students</small>

            <h3><?= $students ?></h3>

        </div>

        <div class="stat-card">

            <small>Teachers</small>

            <h3><?= $teachers ?></h3>

        </div>

        <div class="stat-card">

            <small>Classes</small>

            <h3><?= $classes ?></h3>

        </div>

        <div class="stat-card">

            <small>Subjects</small>

            <h3><?= $subjects ?></h3>

        </div>

    </div>

    <div class="content-panel">

        <h3>Recently Registered Students</h3>

        <div class="table-wrapper">

            <table class="hibs-table">

                <thead>

                <tr>

                    <th>Student</th>
                    <th>Student ID</th>
                    <th>Class</th>
                    <th>Gender</th>
                    <th>Status</th>

                </tr>

                </thead>

                <tbody>

                <?php if (!$recentStudents): ?>

                    <tr>

                        <td colspan="5">
                            No students have been registered yet.
                        </td>

                    </tr>

                <?php endif; ?>

                <?php foreach ($recentStudents as $student): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $student["first_name"] . " " .
                                $student["last_name"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $student["student_id"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $student["class_name"] ?? "Not assigned"
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

                </tbody>

            </table>

        </div>

    </div>

</main>

</body>
</html>
