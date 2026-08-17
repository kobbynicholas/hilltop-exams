<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $class_name = trim($_POST["class_name"] ?? "");
    $class_level = trim($_POST["class_level"] ?? "");

    if ($class_name === "") {

        $error = "Please enter a class name.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO classes
            (class_name, class_level)
            VALUES (?, ?)
        ");

        $stmt->execute([
            $class_name,
            $class_level
        ]);

        $message = "Class added successfully.";
    }
}

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    $stmt = $conn->prepare(
        "DELETE FROM classes WHERE id = ?"
    );

    $stmt->execute([$id]);

    header("Location: classes.php");
    exit;
}

$classes = $conn->query("
    SELECT
        c.id,
        c.class_name,
        c.class_level,
        COUNT(s.id) AS student_count
    FROM classes c
    LEFT JOIN students s
        ON s.class_id = c.id
    GROUP BY
        c.id,
        c.class_name,
        c.class_level
    ORDER BY c.class_name
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>HIBS Reports | Classes</title>

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
    <a href="students.php">Students</a>
    <a href="classes.php" class="active">Classes</a>
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

            <h2>Classes</h2>

            <p>
                Manage HIBS academic classes and year groups.
            </p>

        </div>

    </div>

    <?php if ($message): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <div class="form-panel" style="margin-bottom:25px;">

        <h3 style="color:#641c2b;margin-bottom:20px;font-weight:normal;">
            Add New Class
        </h3>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>Class Name</label>

                    <input
                        type="text"
                        name="class_name"
                        placeholder="e.g. Year 7"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Class Level</label>

                    <input
                        type="text"
                        name="class_level"
                        placeholder="e.g. Lower Secondary"
                    >

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Add Class
                </button>

            </div>

        </form>

    </div>

    <div class="content-panel">

        <h3>Registered Classes</h3>

        <div class="table-wrapper">

            <table class="hibs-table">

                <thead>

                <tr>

                    <th>#</th>
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
                            <?= $class["id"] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $class["class_name"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $class["class_level"] ?? ""
                            ) ?>
                        </td>

                        <td>
                            <?= $class["student_count"] ?>
                        </td>

                        <td>

                            <a
                                href="classes.php?delete=<?= $class["id"] ?>"
                                class="btn btn-danger"
                                onclick="return confirm('Delete this class?')"
                            >
                                Delete
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$classes): ?>

                    <tr>

                        <td colspan="5">
                            No classes have been created yet.
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
