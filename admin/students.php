<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare("
        SELECT
            s.*,
            c.class_name
        FROM students s
        LEFT JOIN classes c
            ON c.id = s.class_id
        WHERE
            s.student_id LIKE ?
            OR s.first_name LIKE ?
            OR s.middle_name LIKE ?
            OR s.last_name LIKE ?
        ORDER BY s.last_name, s.first_name
    ");

    $term = "%" . $search . "%";

    $stmt->execute([
        $term,
        $term,
        $term,
        $term
    ]);

    $students = $stmt->fetchAll();

} else {

    $students = $conn->query("
        SELECT
            s.*,
            c.class_name
        FROM students s
        LEFT JOIN classes c
            ON c.id = s.class_id
        ORDER BY s.last_name, s.first_name
    ")->fetchAll();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>HIBS Reports | Students</title>

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

    <a href="dashboard.php">Dashboard</a>
    <a href="students.php" class="active">Students</a>
    <a href="classes.php">Classes</a>
    <a href="subjects.php">Subjects</a>
    <a href="teachers.php">Teachers</a>
    <a href="marks.php">Marks</a>
    <a href="attendance.php">Attendance</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>

</nav>

<main class="page">

    <div class="page-heading">

        <div>

            <h2>Students</h2>

            <p>
                Student academic records and registration.
            </p>

        </div>

        <a
            href="student_add.php"
            class="btn btn-primary"
        >
            + Register Student
        </a>

    </div>

    <div class="content-panel">

        <form method="GET" class="search-bar">

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search by student name or ID..."
            >

            <button
                type="submit"
                class="btn btn-primary"
            >
                Search
            </button>

            <?php if ($search): ?>

                <a
                    href="students.php"
                    class="btn btn-light"
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>

        <div class="table-wrapper">

            <table class="hibs-table">

                <thead>

                <tr>

                    <th>Photo</th>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Gender</th>
                    <th>Class</th>
                    <th>Status</th>
                    <th>Action</th>

                </tr>

                </thead>

                <tbody>

                <?php foreach ($students as $student): ?>

                    <tr>

                        <td>

                            <?php if (
                                !empty($student["photo"]) &&
                                file_exists("../uploads/students/" . $student["photo"])
                            ): ?>

                                <img
                                    src="../uploads/students/<?= htmlspecialchars($student["photo"]) ?>"
                                    class="student-photo"
                                    alt="Student"
                                >

                            <?php else: ?>

                                <div class="no-photo">
                                    <?= strtoupper(
                                        substr($student["first_name"], 0, 1)
                                    ) ?>
                                </div>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $student["student_id"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $student["first_name"] . " " .
                                ($student["middle_name"] ?
                                    $student["middle_name"] . " " : "") .
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
                                $student["class_name"] ?? "Not assigned"
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $student["status"]
                            ) ?>
                        </td>

                        <td>

                            <a
                                href="student_edit.php?id=<?= $student["id"] ?>"
                                class="btn btn-light"
                            >
                                Edit
                            </a>

                            <a
                                href="student_delete.php?id=<?= $student["id"] ?>"
                                class="btn btn-danger"
                                onclick="return confirm('Are you sure you want to delete this student?')"
                            >
                                Delete
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$students): ?>

                    <tr>

                        <td colspan="7">
                            No students found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

</body>
</html>
