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
| GET CLASS SUBJECTS
|--------------------------------------------------------------------------
*/

$classSubjects = $conn->query("
    SELECT
        cs.id,
        cs.class_id,
        cs.subject_id,
        c.class_name,
        s.subject_name,
        s.subject_code
    FROM class_subjects cs

    INNER JOIN classes c
        ON c.id = cs.class_id

    INNER JOIN subjects s
        ON s.id = cs.subject_id

    ORDER BY
        c.class_name,
        s.subject_name
")->fetchAll();


/*
|--------------------------------------------------------------------------
| ADD ASSESSMENT TO SUBJECT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $class_id = (int)($_POST["class_id"] ?? 0);
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $component_id = (int)($_POST["component_id"] ?? 0);

    if (
        $class_id <= 0 ||
        $subject_id <= 0 ||
        $component_id <= 0
    ) {

        $error = "Please complete all fields.";

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO subject_assessments
                (
                    class_id,
                    subject_id,
                    component_id
                )
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $class_id,
                $subject_id,
                $component_id
            ]);

            $message = "Assessment component assigned successfully.";

        } catch (PDOException $e) {

            $error = "This component is already assigned.";
        }
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
        DELETE FROM subject_assessments
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    header("Location: subject_assessments.php");

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
    WHERE status = 'Active'
    ORDER BY component_name
")->fetchAll();


/*
|--------------------------------------------------------------------------
| GET EXISTING CONFIGURATION
|--------------------------------------------------------------------------
*/

$configurations = $conn->query("
    SELECT
        sa.id,
        c.class_name,
        s.subject_name,
        s.subject_code,
        ac.component_name,
        ac.max_score,
        ac.weight
    FROM subject_assessments sa

    INNER JOIN classes c
        ON c.id = sa.class_id

    INNER JOIN subjects s
        ON s.id = sa.subject_id

    INNER JOIN assessment_components ac
        ON ac.id = sa.component_id

    ORDER BY
        c.class_name,
        s.subject_name,
        sa.id
")->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | Subject Assessments</title>

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
    <a href="subject_assessments.php" class="active">Assessment Setup</a>
    <a href="marks.php">Marks</a>
    <a href="attendance.php">Attendance</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>

</nav>


<main class="page">

    <div class="page-heading">

        <div>

            <h2>Assessment Setup</h2>

            <p>
                Define assessment components for each class and subject.
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
            Configure Subject Assessment
        </h3>


        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>Class</label>

                    <select
                        name="class_id"
                        required
                    >

                        <option value="">
                            Select Class
                        </option>

                        <?php

                        $usedClasses = [];

                        foreach ($classSubjects as $cs):

                            $key =
                                $cs["class_id"] .
                                "-" .
                                $cs["subject_id"];

                        ?>

                            <option
                                value="<?= $cs["class_id"] ?>"
                            >

                                <?= htmlspecialchars(
                                    $cs["class_name"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>Subject</label>

                    <select
                        name="subject_id"
                        required
                    >

                        <option value="">
                            Select Subject
                        </option>

                        <?php foreach ($classSubjects as $cs): ?>

                            <option
                                value="<?= $cs["subject_id"] ?>"
                            >

                                <?= htmlspecialchars(
                                    $cs["subject_name"]
                                ) ?>

                                (<?= htmlspecialchars(
                                    $cs["subject_code"]
                                ) ?>)

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>Assessment Component</label>

                    <select
                        name="component_id"
                        required
                    >

                        <option value="">
                            Select Component
                        </option>

                        <?php foreach ($components as $component): ?>

                            <option
                                value="<?= $component["id"] ?>"
                            >

                                <?= htmlspecialchars(
                                    $component["component_name"]
                                ) ?>

                                —
                                <?= $component["weight"] ?>%

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Add Assessment Component
            </button>

        </form>

    </div>


    <div class="content-panel">

        <h3>Configured Assessments</h3>

        <div class="table-wrapper">

            <table class="hibs-table">

                <thead>

                <tr>

                    <th>Class</th>
                    <th>Subject</th>
                    <th>Component</th>
                    <th>Max Score</th>
                    <th>Weight</th>
                    <th>Action</th>

                </tr>

                </thead>

                <tbody>

                <?php foreach ($configurations as $config): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $config["class_name"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $config["subject_name"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $config["component_name"]
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                $config["max_score"],
                                2
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                $config["weight"],
                                2
                            ) ?>%
                        </td>

                        <td>

                            <a
                                href="subject_assessments.php?delete=<?= $config["id"] ?>"
                                class="btn btn-danger"
                                onclick="return confirm('Remove this assessment component?')"
                            >
                                Remove
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$configurations): ?>

                    <tr>

                        <td colspan="6">
                            No assessment configurations have been created.
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
