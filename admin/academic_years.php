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

    $academic_year = trim($_POST["academic_year"] ?? "");

    if ($academic_year === "") {

        $error = "Enter an academic year.";

    } else {

        try {

            $conn->beginTransaction();

            $stmt = $conn->prepare("
                INSERT INTO academic_years
                (academic_year, status)
                VALUES (?, 'Inactive')
            ");

            $stmt->execute([$academic_year]);

            $conn->commit();

            $message = "Academic year added.";

        } catch (PDOException $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error = "Unable to add academic year.";
        }
    }
}

if (isset($_GET["activate"])) {

    $id = (int) $_GET["activate"];

    $conn->beginTransaction();

    $conn->exec("
        UPDATE academic_years
        SET status = 'Inactive'
    ");

    $stmt = $conn->prepare("
        UPDATE academic_years
        SET status = 'Active'
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $conn->commit();

    header("Location: academic_years.php");
    exit;
}

$years = $conn->query("
    SELECT *
    FROM academic_years
    ORDER BY id DESC
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | Academic Years</title>

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

        <a href="../logout.php" class="logout-link">
            Sign out
        </a>

    </div>

</header>

<nav class="hibs-nav">

    <a href="dashboard.php">Dashboard</a>
    <a href="students.php">Students</a>
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

            <h2>Academic Years</h2>

            <p>
                Manage the school's academic years.
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

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>Academic Year</label>

                    <input
                        type="text"
                        name="academic_year"
                        placeholder="e.g. 2026/2027"
                        required
                    >

                </div>

            </div>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Add Academic Year
            </button>

        </form>

    </div>

    <div class="content-panel">

        <h3>Academic Years</h3>

        <table class="hibs-table">

            <thead>

            <tr>

                <th>Academic Year</th>
                <th>Status</th>
                <th>Action</th>

            </tr>

            </thead>

            <tbody>

            <?php foreach ($years as $year): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars(
                            $year["academic_year"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $year["status"]
                        ) ?>
                    </td>

                    <td>

                        <?php if ($year["status"] !== "Active"): ?>

                            <a
                                href="academic_years.php?activate=<?= $year["id"] ?>"
                                class="btn btn-gold"
                            >
                                Make Active
                            </a>

                        <?php else: ?>

                            <span class="btn btn-light">
                                Current Year
                            </span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>
