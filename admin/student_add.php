<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$error = "";

$classes = $conn->query("
    SELECT id, class_name
    FROM classes
    ORDER BY class_name
")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = trim($_POST["student_id"] ?? "");
    $first_name = trim($_POST["first_name"] ?? "");
    $middle_name = trim($_POST["middle_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $gender = $_POST["gender"] ?? "";
    $dob = $_POST["date_of_birth"] ?? "";
    $class_id = !empty($_POST["class_id"])
        ? (int) $_POST["class_id"]
        : null;

    if (
        $student_id === "" ||
        $first_name === "" ||
        $last_name === "" ||
        $gender === ""
    ) {

        $error = "Please complete all required fields.";

    } else {

        $photoName = null;

        if (
            isset($_FILES["photo"]) &&
            $_FILES["photo"]["error"] === UPLOAD_ERR_OK
        ) {

            $uploadDir = "../uploads/students/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $extension = strtolower(
                pathinfo(
                    $_FILES["photo"]["name"],
                    PATHINFO_EXTENSION
                )
            );

            $allowed = ["jpg", "jpeg", "png", "webp"];

            if (!in_array($extension, $allowed)) {

                $error = "Please upload a JPG, PNG or WEBP image.";

            } else {

                $photoName =
                    uniqid("student_", true) .
                    "." .
                    $extension;

                move_uploaded_file(
                    $_FILES["photo"]["tmp_name"],
                    $uploadDir . $photoName
                );
            }
        }

        if ($error === "") {

            try {

                $stmt = $conn->prepare("
                    INSERT INTO students
                    (
                        student_id,
                        first_name,
                        middle_name,
                        last_name,
                        gender,
                        date_of_birth,
                        class_id,
                        photo
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $student_id,
                    $first_name,
                    $middle_name ?: null,
                    $last_name,
                    $gender,
                    $dob ?: null,
                    $class_id,
                    $photoName
                ]);

                header("Location: students.php?success=1");
                exit;

            } catch (PDOException $e) {

                $error = "Unable to register student. The Student ID may already exist.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>HIBS Reports | Register Student</title>

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
    <a href="students.php" class="active">Students</a>
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

            <h2>Register Student</h2>

            <p>
                Add a new student to the HIBS academic records.
            </p>

        </div>

    </div>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <div class="form-panel">

        <form method="POST"
              enctype="multipart/form-data">

            <div class="form-grid">

                <div class="form-group">

                    <label>Student ID *</label>

                    <input
                        type="text"
                        name="student_id"
                        placeholder="e.g. HIBS2026001"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Class</label>

                    <select name="class_id">

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

                    <label>First Name *</label>

                    <input
                        type="text"
                        name="first_name"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Middle Name</label>

                    <input
                        type="text"
                        name="middle_name"
                    >

                </div>

                <div class="form-group">

                    <label>Last Name *</label>

                    <input
                        type="text"
                        name="last_name"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Gender *</label>

                    <select name="gender" required>

                        <option value="">
                            Select Gender
                        </option>

                        <option value="Male">
                            Male
                        </option>

                        <option value="Female">
                            Female
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label>Date of Birth</label>

                    <input
                        type="date"
                        name="date_of_birth"
                    >

                </div>

                <div class="form-group">

                    <label>Student Photograph</label>

                    <input
                        type="file"
                        name="photo"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Register Student
                </button>

                <a
                    href="students.php"
                    class="btn btn-light"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</main>

</body>
</html>
