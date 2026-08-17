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

    $code = strtoupper(trim($_POST["subject_code"] ?? ""));
    $name = trim($_POST["subject_name"] ?? "");

    if ($code === "" || $name === "") {

        $error = "Subject code and subject name are required.";

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO subjects
                (subject_code, subject_name)
                VALUES (?, ?)
            ");

            $stmt->execute([
                $code,
                $name
            ]);

            $message = "Subject added successfully.";

        } catch (PDOException $e) {

            $error = "That subject code already exists.";
        }
    }
}

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    try {

        $stmt = $conn->prepare(
            "DELETE FROM subjects WHERE id = ?"
        );

        $stmt->execute([$id]);

        $message = "Subject deleted successfully.";

    } catch (PDOException $e) {

        $error = "This subject cannot be deleted because it is being used.";
    }
}

$subjects = $conn->query("
    SELECT *
    FROM subjects
    ORDER BY subject_name
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | Subjects</title>

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
    <a href="classes.php">Classes</a>
    <a href="subjects.php" class="active">Subjects</a>
    <a href="teachers.php">Teachers</a>
    <a href="marks.php">Marks</a>
    <a href="attendance.php">Attendance</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>

</nav>

<main class="page">

    <div class="page-heading">

        <div>

            <h2>Subjects</h2>

            <p>
                Manage the academic subjects offered by HIBS.
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
            Add Subject
        </h3>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>Subject Code *</label>

                    <input
                        type="text"
                        name="subject_code"
                        placeholder="e.g. PHY"
                        maxlength="30"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Subject Name *</label>

                    <input
                        type="text"
                        name="subject_name"
                        placeholder="e.g. Physics"
                        required
                    >

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Add Subject
                </button>

            </div>

        </form>

    </div>

    <div class="content-panel">

        <h3>Academic Subjects</h3>

        <table class="hibs-table">

            <thead>

            <tr>

                <th>#</th>
                <th>Code</th>
                <th>Subject</th>
                <th>Action</th>

            </tr>

            </thead>

            <tbody>

            <?php foreach ($subjects as $subject): ?>

                <tr>

                    <td>
                        <?= $subject["id"] ?>
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
                            href="subjects.php?delete=<?= $subject["id"] ?>"
                            class="btn btn-danger"
                            onclick="return confirm('Delete this subject?')"
                        >
                            Delete
                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>
