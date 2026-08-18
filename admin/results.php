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

$class_id = (int)($_GET["class_id"] ?? 0);
$term_id = (int)($_GET["term_id"] ?? 0);

$classes = $conn->query("
    SELECT id, class_name
    FROM classes
    ORDER BY class_name
")->fetchAll();

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
        t.id
")->fetchAll();

$results = [];

$class = null;
$term = null;

if ($class_id > 0 && $term_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            c.class_name
        FROM classes c
        WHERE c.id = ?
    ");

    $stmt->execute([$class_id]);

    $class = $stmt->fetch();


    $stmt = $conn->prepare("
        SELECT
            t.term_name,
            ay.academic_year
        FROM terms t

        INNER JOIN academic_years ay
            ON ay.id = t.academic_year_id

        WHERE t.id = ?
    ");

    $stmt->execute([$term_id]);

    $term = $stmt->fetch();


    $stmt = $conn->prepare("
        SELECT
            sr.*,

            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name

        FROM student_results sr

        INNER JOIN students s
            ON s.id = sr.student_id

        WHERE
            sr.class_id = ?
            AND sr.term_id = ?

        ORDER BY
            sr.position ASC
    ");

    $stmt->execute([
        $class_id,
        $term_id
    ]);

    $results = $stmt->fetchAll();
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    HIBS Reports | Results
</title>

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

    <a href="dashboard.php">
        Dashboard
    </a>

    <a href="students.php">
        Students
    </a>

    <a href="classes.php">
        Classes
    </a>

    <a href="subjects.php">
        Subjects
    </a>

    <a href="teachers.php">
        Teachers
    </a>

    <a href="academic_years.php">
        Academic Years
    </a>

    <a href="terms.php">
        Terms
    </a>

    <a href="class_subjects.php">
        Class Subjects
    </a>

    <a href="assessments.php">
        Assessments
    </a>

    <a href="subject_assessments.php">
        Assessment Setup
    </a>

    <a href="grades.php">
        Grades
    </a>

    <a href="marks.php">
        Marks
    </a>

    <a href="results.php"
       class="active">
        Results
    </a>

    <a href="attendance.php">
        Attendance
    </a>

    <a href="reports.php">
        Reports
    </a>

</nav>


<main class="page">

    <div class="page-heading">

        <div>

            <h2>
                Academic Results
            </h2>

            <p>
                Calculate and review student academic performance.
            </p>

        </div>

    </div>


    <?php if (isset($_GET["calculated"])): ?>

        <div class="alert alert-success">

            Results calculated successfully.

        </div>

    <?php endif; ?>


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

                        <?php foreach ($classes as $item): ?>

                            <option
                                value="<?= $item["id"] ?>"
                                <?= $class_id === (int)$item["id"]
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= htmlspecialchars(
                                    $item["class_name"]
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

                        <?php foreach ($terms as $item): ?>

                            <option
                                value="<?= $item["id"] ?>"
                                <?= $term_id === (int)$item["id"]
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= htmlspecialchars(
                                    $item["academic_year"]
                                ) ?>

                                —

                                <?= htmlspecialchars(
                                    $item["term_name"]
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
                View Results
            </button>


            <?php if ($class_id > 0 && $term_id > 0): ?>

                <a
                    href="calculate_results.php?class_id=<?= $class_id ?>&term_id=<?= $term_id ?>"
                    class="btn btn-gold"
                    onclick="return confirm('Calculate or recalculate results for this class and term?')"
                >
                    Calculate Results
                </a>

            <?php endif; ?>

        </form>

    </div>


    <?php if ($results): ?>


        <div class="content-panel">

            <div style="margin-bottom:20px;">

                <h3>
                    <?= htmlspecialchars(
                        $class["class_name"]
                    ) ?>
                </h3>

                <p style="
                    font-family:Arial;
                    color:#777;
                    font-size:12px;
                ">

                    <?= htmlspecialchars(
                        $term["academic_year"]
                    ) ?>

                    ·

                    <?= htmlspecialchars(
                        $term["term_name"]
                    ) ?>

                </p>

            </div>


            <div class="table-wrapper">

                <table class="hibs-table">

                    <thead>

                    <tr>

                        <th>Position</th>

                        <th>Student ID</th>

                        <th>Student</th>

                        <th>Total Score</th>

                        <th>Average</th>

                        <th>Class Size</th>

                    </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($results as $result): ?>

                        <tr>

                            <td>

                                <strong>

                                    <?= (int)$result["position"] ?>

                                    <?php if (
                                        $result["position"] == 1
                                    ): ?>

                                        <span
                                            style="
                                                color:#b58a3a;
                                                margin-left:5px;
                                            "
                                        >
                                            ★
                                        </span>

                                    <?php endif; ?>

                                </strong>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $result["student_id"]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $result["last_name"]
                                ) ?>,

                                <?= htmlspecialchars(
                                    $result["first_name"]
                                ) ?>

                                <?php if (
                                    $result["middle_name"]
                                ): ?>

                                    <?= htmlspecialchars(
                                        " " .
                                        $result["middle_name"]
                                    ) ?>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= number_format(
                                    $result["total_score"],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <strong>

                                    <?= number_format(
                                        $result["average_score"],
                                        2
                                    ) ?>%

                                </strong>

                            </td>


                            <td>

                                <?= (int)$result["class_size"] ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>


    <?php elseif ($class_id && $term_id): ?>


        <div class="alert alert-danger">

            No calculated results were found.

            Click
            <strong>
                Calculate Results
            </strong>
            after marks have been entered.

        </div>


    <?php endif; ?>

</main>

</body>

</html>
