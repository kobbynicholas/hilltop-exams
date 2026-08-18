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
| ADD SUBJECT TO CLASS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $class_id = (int)($_POST["class_id"] ?? 0);
    $subject_id = (int)($_POST["subject_id"] ?? 0);

    if ($class_id <= 0 || $subject_id <= 0) {

        $error = "Please select both a class and a subject.";

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO class_subjects
                (class_id, subject_id)
                VALUES (?, ?)
            ");

            $stmt->execute([
                $class_id,
                $subject_id
            ]);

            $message = "Subject assigned to class successfully.";

        } catch (PDOException $e) {

            $error = "This subject is already assigned to this class.";
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
        DELETE FROM class_subjects
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    header("Location: class_subjects.php");
    exit;
}

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
| GET SUBJECTS
|--------------------------------------------------------------------------
*/

$subjects = $conn->query("
    SELECT id, subject_code, subject_name
    FROM subjects
    ORDER BY subject_name
")->fetchAll();

/*
|--------------------------------------------------------------------------
| GET ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$assignments = $conn->query("
    SELECT
        cs.id,
        c.class_name,
        s.subject_code,
        s.subject_name
    FROM class_subjects cs

    INNER JOIN classes c
        ON c.id = cs.class_id

    INNER JOIN subjects s
        ON s.id = cs.subject_id

    ORDER BY
        c.class_name,
        s.subject_name
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | Class Subjects</title>

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
    <a href="class_subjects.php" class="active">Class Subjects</a>
    <a href="marks.php">Marks</a>
    <a href="attendance.php">Attendance</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>

</nav>

<main class="page">

    <div class="page-heading">

        <div>

            <h2>Class Subjects</h2>

            <p>
                Define the subjects offered by each HIBS class.
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
            Assign Subject to Class
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

                        <?php foreach ($classes as $class): ?>

                            <option value="<?= $class["id"] ?>">

                                <?= htmlspecialchars(
                                    $class["class_name"]
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

                        <?php foreach ($subjects as $subject): ?>

                            <option value="<?= $subject["id"] ?>">

                                <?= htmlspecialchars(
                                    $subject["subject_name"]
                                ) ?>

                                —
                                <?= htmlspecialchars(
                                    $subject["subject_code"]
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
                Add Subject to Class
            </button>

        </form>

    </div>


    <div class="content-panel">

        <h3>Class Subject Structure</h3>

        <div class="table-wrapper">

            <table class="hibs-table">

                <thead>

                <tr>

                    <th>Class</th>
                    <th>Subject Code</th>
                    <th>Subject</th>
                    <th>Action</th>

                </tr>

                </thead>

                <tbody>

                <?php foreach ($assignments as $assignment): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $assignment["class_name"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $assignment["subject_code"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $assignment["subject_name"]
                            ) ?>
                        </td>

                        <td>

                            <a
                                href="class_subjects.php?delete=<?= $assignment["id"] ?>"
                                class="btn btn-danger"
                                onclick="return confirm('Remove this subject from the class?')"
                            >
                                Remove
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$assignments): ?>

                    <tr>

                        <td colspan="4">
                            No class subjects have been configured.
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
