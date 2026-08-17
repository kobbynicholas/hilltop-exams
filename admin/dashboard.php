<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.php");
    exit;

}

if ($_SESSION["role"] !== "admin") {

    header("Location: ../login.php");
    exit;

}

/* COUNTS */

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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>HIBS Reports - Dashboard</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

</head>

<body>

<div class="sidebar">

    <div class="sidebar-logo">

        <div class="school-icon">H</div>

        <h2>HIBS REPORTS</h2>

        <small>Administrator</small>

    </div>

    <nav>

        <a href="dashboard.php" class="active">
            🏠 Dashboard
        </a>

        <a href="students.php">
            👨‍🎓 Students
        </a>

        <a href="teachers.php">
            👩‍🏫 Teachers
        </a>

        <a href="classes.php">
            🏫 Classes
        </a>

        <a href="subjects.php">
            📚 Subjects
        </a>

        <a href="marks.php">
            📝 Marks Entry
        </a>

        <a href="attendance.php">
            📅 Attendance
        </a>

        <a href="reports.php">
            📊 Reports
        </a>

        <a href="settings.php">
            ⚙️ Settings
        </a>

        <a href="../logout.php">
            🚪 Logout
        </a>

    </nav>

</div>

<div class="main-content">

    <header class="topbar">

        <div>

            <h1>Dashboard</h1>

            <p>
                Welcome,
                <?= htmlspecialchars($_SESSION["full_name"]) ?>
            </p>

        </div>

        <div class="admin-badge">
            ADMIN
        </div>

    </header>

    <section class="cards">

        <div class="dashboard-card">

            <div class="card-icon">👨‍🎓</div>

            <div>
                <h3><?= $students ?></h3>
                <p>Students</p>
            </div>

        </div>

        <div class="dashboard-card">

            <div class="card-icon">👩‍🏫</div>

            <div>
                <h3><?= $teachers ?></h3>
                <p>Teachers</p>
            </div>

        </div>

        <div class="dashboard-card">

            <div class="card-icon">🏫</div>

            <div>
                <h3><?= $classes ?></h3>
                <p>Classes</p>
            </div>

        </div>

        <div class="dashboard-card">

            <div class="card-icon">📚</div>

            <div>
                <h3><?= $subjects ?></h3>
                <p>Subjects</p>
            </div>

        </div>

    </section>

    <section class="welcome-box">

        <h2>HIBS Academic Reports System</h2>

        <p>
            Manage students, teachers, classes, subjects,
            academic marks, attendance and student reports
            from one central system.
        </p>

    </section>

</div>

</body>
</html>
