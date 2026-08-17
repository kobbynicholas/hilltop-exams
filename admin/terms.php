<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error = "";

$years = $conn->query("
    SELECT *
    FROM academic_years
    ORDER BY id DESC
")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $term_name = trim($_POST["term_name"] ?? "");
    $academic_year_id = (int) ($_POST["academic_year_id"] ?? 0);

    if ($term_name === "" || $academic_year_id <= 0) {

        $error = "Please select an academic year and enter a term.";

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO terms
                (
                    term_name,
                    academic_year_id,
                    status
                )
                VALUES (?, ?, 'Inactive')
            ");

            $stmt->execute([
                $term_name,
                $academic_year_id
            ]);

            $message = "Term added successfully.";

        } catch (PDOException $e) {

            $error = "Unable to add the term.";
        }
    }
}

if (isset($_GET["activate"])) {

    $term_id = (int) $_GET["activate"];

    $stmt = $conn->prepare("
        SELECT academic_year_id
        FROM terms
        WHERE id = ?
    ");

    $stmt->execute([$term_id]);

    $term = $stmt->fetch();

    if ($term) {

        $stmt = $conn->prepare("
            UPDATE terms
            SET status = 'Inactive'
            WHERE academic_year_id = ?
        ");

        $stmt->execute([
            $term["academic_year_id"]
        ]);

        $stmt = $conn->prepare("
            UPDATE terms
            SET status = 'Active'
            WHERE id = ?
        ");

        $stmt->execute([$term_id]);
    }

    header("Location: terms.php");
    exit;
}

$terms = $conn->query("
    SELECT
        t.*,
        ay.academic_year
    FROM terms t
    INNER JOIN academic_years ay
        ON ay.id = t.academic_year_id
    ORDER BY ay.id DESC, t.id
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | Terms</title>

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

            <h2>Academic Terms</h2>

            <p>
                Manage terms within each academic year.
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

                    <select
                        name="academic_year_id"
                        required
                    >

                        <option value="">
                            Select Academic Year
                        </option>

                        <?php foreach ($years as $year): ?>

                            <option value="<?= $year["id"] ?>">

                                <?= htmlspecialchars(
                                    $year["academic_year"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label>Term</label>

                    <select
                        name="term_name"
                        required
                    >

                        <option value="">
                            Select Term
                        </option>

                        <option value="Term 1">
                            Term 1
                        </option>

                        <option value="Term 2">
                            Term 2
                        </option>

                        <option value="Term 3">
                            Term 3
                        </option>

                    </select>

                </div>

            </div>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Add Term
            </button>

        </form>

    </div>

    <div class="content-panel">

        <h3>Registered Terms</h3>

        <table class="hibs-table">

            <thead>

            <tr>

                <th>Academic Year</th>
                <th>Term</th>
                <th>Status</th>
                <th>Action</th>

            </tr>

            </thead>

            <tbody>

            <?php foreach ($terms as $term): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars(
                            $term["academic_year"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $term["term_name"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $term["status"]
                        ) ?>
                    </td>

                    <td>

                        <?php if ($term["status"] !== "Active"): ?>

                            <a
                                href="terms.php?activate=<?= $term["id"] ?>"
                                class="btn btn-gold"
                            >
                                Make Active
                            </a>

                        <?php else: ?>

                            <span class="btn btn-light">
                                Current Term
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
