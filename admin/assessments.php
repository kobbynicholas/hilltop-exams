<?php

session_start();

require_once "../config/db.php";

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| ADD COMPONENT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["component_name"] ?? "");
    $max_score = (float)($_POST["max_score"] ?? 0);
    $weight = (float)($_POST["weight"] ?? 0);

    if (
        $name === "" ||
        $max_score <= 0 ||
        $weight <= 0
    ) {

        $error = "Please enter valid assessment information.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO assessment_components
            (
                component_name,
                max_score,
                weight
            )
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $name,
            $max_score,
            $weight
        ]);

        $message = "Assessment component added successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $id = (int)$_GET["delete"];

    $stmt = $conn->prepare("
        DELETE FROM assessment_components
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    header("Location: assessments.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET COMPONENTS
|--------------------------------------------------------------------------
*/

$components = $conn->query("
    SELECT *
    FROM assessment_components
    ORDER BY id
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | Assessments</title>

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

    <a href="dashboard.php">Dashboard</a>
    <a href="students.php">Students</a>
    <a href="classes.php">Classes</a>
    <a href="subjects.php">Subjects</a>
    <a href="teachers.php">Teachers</a>
    <a href="academic_years.php">Academic Years</a>
    <a href="terms.php">Terms</a>
    <a href="class_subjects.php">Class Subjects</a>
    <a href="assessments.php" class="active">Assessments</a>
    <a href="marks.php">Marks</a>
    <a href="attendance.php">Attendance</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>

</nav>


<main class="page">

    <div class="page-heading">

        <div>

            <h2>Assessment Components</h2>

            <p>
                Configure the components used to calculate academic results.
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


    <div class="form-panel"
         style="margin-bottom:25px;">

        <h3 style="
            color:#641c2b;
            margin-bottom:20px;
            font-weight:normal;
        ">
            Add Assessment Component
        </h3>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>Component Name</label>

                    <input
                        type="text"
                        name="component_name"
                        placeholder="e.g. Continuous Assessment"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Maximum Score</label>

                    <input
                        type="number"
                        name="max_score"
                        step="0.01"
                        min="1"
                        value="100"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Weight (%)</label>

                    <input
                        type="number"
                        name="weight"
                        step="0.01"
                        min="0.01"
                        max="100"
                        placeholder="e.g. 30"
                        required
                    >

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Add Component
            </button>

        </form>

    </div>


    <div class="content-panel">

        <h3>Assessment Components</h3>

        <table class="hibs-table">

            <thead>

            <tr>

                <th>Component</th>
                <th>Maximum Score</th>
                <th>Weight</th>
                <th>Action</th>

            </tr>

            </thead>

            <tbody>

            <?php foreach ($components as $component): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars(
                            $component["component_name"]
                        ) ?>
                    </td>

                    <td>
                        <?= number_format(
                            $component["max_score"],
                            2
                        ) ?>
                    </td>

                    <td>
                        <?= number_format(
                            $component["weight"],
                            2
                        ) ?>%
                    </td>

                    <td>

                        <a
                            href="assessments.php?delete=<?= $component["id"] ?>"
                            class="btn btn-danger"
                            onclick="return confirm('Delete this component?')"
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
