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
| GET CLASSES
|--------------------------------------------------------------------------
*/

$classes = $conn->query("
    SELECT id, class_name
    FROM classes
    ORDER BY class_name
")->fetchAll();

/*
|--------------------------------------------------------------------------
| GET TERMS
|--------------------------------------------------------------------------
*/

$terms = $conn->query("
    SELECT
        t.id,
        t.term_name,
        ay.academic_year
    FROM terms t

    INNER JOIN academic_years ay
        ON ay.id = t.academic_year_id

    ORDER BY
        ay.id DESC,
        t.id DESC
")->fetchAll();


/*
|--------------------------------------------------------------------------
| PARAMETERS
|--------------------------------------------------------------------------
*/

$class_id = (int)($_GET["class_id"] ?? 0);
$term_id  = (int)($_GET["term_id"] ?? 0);

$students = [];


/*
|--------------------------------------------------------------------------
| GET STUDENTS
|--------------------------------------------------------------------------
*/

if ($class_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            id,
            student_id,
            first_name,
            middle_name,
            last_name
        FROM students
        WHERE
            class_id = ?
            AND status = 'Active'
        ORDER BY
            last_name,
            first_name
    ");

    $stmt->execute([$class_id]);

    $students = $stmt->fetchAll();
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    HIBS Reports | Report Cards
</title>

<link rel="stylesheet"
      href="../assets/css/style.css">

<style>

.report-card-list {
    background: white;
    border: 1px solid #e5dfd7;
    padding: 25px;
}

.report-card-list h3 {
    color: #641c2b;
    font-weight: normal;
    margin-bottom: 20px;
}

.student-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

</style>

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
            <?= htmlspecialchars(
                $_SESSION["full_name"]
            ) ?>
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
    <a href="grades.php">Grades</a>
    <a href="marks.php">Marks</a>
    <a href="results.php">Results</a>
    <a href="report_cards.php" class="active">Report Cards</a>
    <a href="attendance.php">Attendance</a>
    <a href="reports.php">Reports</a>

</nav>


<main class="page">

    <div class="page-heading">

        <div>

            <h2>
                Student Report Cards
            </h2>

            <p>
                Generate and review official HIBS student reports.
            </p>

        </div>

    </div>


    <div class="form-panel"
         style="margin-bottom:25px;">

        <form method="GET">

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Class
                    </label>

                    <select
                        name="class_id"
                        required
                    >

                        <option value="">
                            Select Class
                        </option>

                        <?php foreach ($classes as $class): ?>

                            <option
                                value="<?= $class["id"] ?>"
                                <?= $class_id === (int)$class["id"]
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= htmlspecialchars(
                                    $class["class_name"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Term
                    </label>

                    <select
                        name="term_id"
                        required
                    >

                        <option value="">
                            Select Term
                        </option>

                        <?php foreach ($terms as $term): ?>

                            <option
                                value="<?= $term["id"] ?>"
                                <?= $term_id === (int)$term["id"]
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= htmlspecialchars(
                                    $term["academic_year"]
                                ) ?>

                                —

                                <?= htmlspecialchars(
                                    $term["term_name"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Load Students
            </button>

        </form>

    </div>


    <?php if ($class_id && $term_id): ?>

        <div class="report-card-list">

            <h3>
                Students
            </h3>


            <div class="table-wrapper">

                <table class="hibs-table">

                    <thead>

                    <tr>

                        <th>#</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Action</th>
                    </tr>

                    </thead>


                    <tbody>

                    <?php $number = 1; ?>

                    <?php foreach ($students as $student): ?>

                        <tr>

                            <td>
                                <?= $number++ ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $student["student_id"]
                                ) ?>
                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $student["last_name"]
                                ) ?>,

                                <?= htmlspecialchars(
                                    $student["first_name"]
                                ) ?>

                                <?php if (
                                    $student["middle_name"]
                                ): ?>

                                    <?= htmlspecialchars(
                                        " " .
                                        $student["middle_name"]
                                    ) ?>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="student-actions">

                                    <a
                                        href="../student_report.php?student_id=<?= $student["id"] ?>&class_id=<?= $class_id ?>&term_id=<?= $term_id ?>"
                                        class="btn btn-primary"
                                        target="_blank"
                                    >
                                        View Report
                                    </a>

                                    <a
                                        href="../student_report.php?student_id=<?= $student["id"] ?>&class_id=<?= $class_id ?>&term_id=<?= $term_id ?>&print=1"
                                        class="btn btn-gold"
                                        target="_blank"
                                    >
                                        Print
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                    <?php if (!$students): ?>

                        <tr>

                            <td colspan="4">

                                No active students were found
                                in this class.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

</main>

</body>

</html>
