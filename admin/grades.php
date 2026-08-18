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
| ADD GRADE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $grade = strtoupper(trim($_POST["grade"] ?? ""));
    $min_score = (float)($_POST["min_score"] ?? 0);
    $max_score = (float)($_POST["max_score"] ?? 0);
    $description = trim($_POST["description"] ?? "");
    $remark = trim($_POST["remark"] ?? "");

    if (
        $grade === "" ||
        $description === "" ||
        $min_score < 0 ||
        $max_score < $min_score ||
        $max_score > 100
    ) {

        $error = "Please enter valid grade information.";

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO grade_scales
                (
                    grade,
                    min_score,
                    max_score,
                    description,
                    remark
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $grade,
                $min_score,
                $max_score,
                $description,
                $remark ?: null
            ]);

            $message = "Grade added successfully.";

        } catch (PDOException $e) {

            $error = "Unable to add the grade.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE GRADE
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $id = (int)$_GET["delete"];

    $stmt = $conn->prepare("
        DELETE FROM grade_scales
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    header("Location: grades.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| GET GRADES
|--------------------------------------------------------------------------
*/

$grades = $conn->query("
    SELECT *
    FROM grade_scales
    ORDER BY min_score DESC
")->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | Grade Scale</title>

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
    <a href="assessments.php">Assessments</a>
    <a href="subject_assessments.php">Assessment Setup</a>
    <a href="grades.php" class="active">Grades</a>
    <a href="marks.php">Marks</a>
    <a href="attendance.php">Attendance</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>

</nav>


<main class="page">

    <div class="page-heading">

        <div>

            <h2>
                Grade Scale
            </h2>

            <p>
                Configure the grading system used by HIBS Reports.
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
            font-weight:normal;
            margin-bottom:20px;
        ">
            Add Grade
        </h3>


        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>Grade</label>

                    <input
                        type="text"
                        name="grade"
                        placeholder="e.g. A"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Minimum Score</label>

                    <input
                        type="number"
                        name="min_score"
                        step="0.01"
                        min="0"
                        max="100"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Maximum Score</label>

                    <input
                        type="number"
                        name="max_score"
                        step="0.01"
                        min="0"
                        max="100"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Description</label>

                    <input
                        type="text"
                        name="description"
                        placeholder="e.g. Excellent"
                        required
                    >

                </div>


                <div class="form-group full">

                    <label>Remark</label>

                    <input
                        type="text"
                        name="remark"
                        placeholder="e.g. Very Good"
                    >

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Add Grade
            </button>

        </form>

    </div>


    <div class="content-panel">

        <h3>
            Current Grading Scale
        </h3>


        <div class="table-wrapper">

            <table class="hibs-table">

                <thead>

                <tr>

                    <th>Grade</th>
                    <th>Minimum</th>
                    <th>Maximum</th>
                    <th>Description</th>
                    <th>Remark</th>
                    <th>Action</th>

                </tr>

                </thead>


                <tbody>

                <?php foreach ($grades as $grade): ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars(
                                    $grade["grade"]
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= number_format(
                                $grade["min_score"],
                                2
                            ) ?>%
                        </td>

                        <td>
                            <?= number_format(
                                $grade["max_score"],
                                2
                            ) ?>%
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $grade["description"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $grade["remark"] ?? "-"
                            ) ?>
                        </td>

                        <td>

                            <a
                                href="grades.php?delete=<?= $grade["id"] ?>"
                                class="btn btn-danger"
                                onclick="return confirm('Delete this grade?')"
                            >
                                Delete
                            </a>

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
