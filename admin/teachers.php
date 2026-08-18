<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| DELETE TEACHER
|--------------------------------------------------------------------------
*/

if (isset($_GET["delete"])) {

    $teacher_id = (int) $_GET["delete"];

    try {

        $stmt = $conn->prepare("
            SELECT user_id
            FROM teachers
            WHERE id = ?
        ");

        $stmt->execute([$teacher_id]);

        $teacher = $stmt->fetch();

        if ($teacher) {

            $stmt = $conn->prepare("
                DELETE FROM users
                WHERE id = ?
                AND role = 'teacher'
            ");

            $stmt->execute([$teacher["user_id"]]);

            $message = "Teacher deleted successfully.";
        }

    } catch (PDOException $e) {

        $error = "Unable to delete teacher.";
    }
}

/*
|--------------------------------------------------------------------------
| REGISTER TEACHER
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $employee_id = trim($_POST["employee_id"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $qualification = trim($_POST["qualification"] ?? "");
    $specialization = trim($_POST["specialization"] ?? "");

    if (
        $full_name === "" ||
        $username === "" ||
        $password === "" ||
        $employee_id === ""
    ) {

        $error = "Please complete all required fields.";

    } else {

        try {

            $conn->beginTransaction();

            $check = $conn->prepare("
                SELECT id
                FROM users
                WHERE username = ?
            ");

            $check->execute([$username]);

            if ($check->fetch()) {

                throw new Exception(
                    "The username is already in use."
                );
            }

            $check = $conn->prepare("
                SELECT id
                FROM teachers
                WHERE employee_id = ?
            ");

            $check->execute([$employee_id]);

            if ($check->fetch()) {

                throw new Exception(
                    "The employee ID already exists."
                );
            }

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare("
                INSERT INTO users
                (
                    full_name,
                    username,
                    password,
                    role,
                    status
                )
                VALUES (?, ?, ?, 'teacher', 'active')
            ");

            $stmt->execute([
                $full_name,
                $username,
                $hashedPassword
            ]);

            $user_id = $conn->lastInsertId();

            $stmt = $conn->prepare("
                INSERT INTO teachers
                (
                    user_id,
                    employee_id,
                    phone,
                    qualification,
                    specialization
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $user_id,
                $employee_id,
                $phone ?: null,
                $qualification ?: null,
                $specialization ?: null
            ]);

            $conn->commit();

            $message = "Teacher registered successfully.";

        } catch (Exception $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET TEACHERS
|--------------------------------------------------------------------------
*/

$teachers = $conn->query("
    SELECT
        t.id,
        t.employee_id,
        t.phone,
        t.qualification,
        t.specialization,
        u.full_name,
        u.username,
        u.status
    FROM teachers t
    INNER JOIN users u
        ON u.id = t.user_id
    ORDER BY u.full_name
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | Teachers</title>

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
    <a href="subjects.php">Subjects</a>
    <a href="teachers.php" class="active">Teachers</a>
    <a href="marks.php">Marks</a>
    <a href="attendance.php">Attendance</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>

</nav>

<main class="page">

    <div class="page-heading">

        <div>

            <h2>Teachers</h2>

            <p>
                Manage HIBS teaching staff and academic assignments.
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
            Register Teacher
        </h3>

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>Full Name *</label>

                    <input
                        type="text"
                        name="full_name"
                        placeholder="Teacher's full name"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Employee ID *</label>

                    <input
                        type="text"
                        name="employee_id"
                        placeholder="e.g. HIBS-T001"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Username *</label>

                    <input
                        type="text"
                        name="username"
                        placeholder="Login username"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Temporary Password *</label>

                    <input
                        type="password"
                        name="password"
                        placeholder="Teacher login password"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Phone</label>

                    <input
                        type="text"
                        name="phone"
                        placeholder="Phone number"
                    >

                </div>

                <div class="form-group">

                    <label>Qualification</label>

                    <input
                        type="text"
                        name="qualification"
                        placeholder="e.g. B.Ed., M.Ed."
                    >

                </div>

                <div class="form-group full">

                    <label>Specialization</label>

                    <input
                        type="text"
                        name="specialization"
                        placeholder="e.g. Physics / Mathematics"
                    >

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Register Teacher
                </button>

            </div>

        </form>

    </div>

    <div class="content-panel">

        <h3>Teaching Staff</h3>

        <div class="table-wrapper">

            <table class="hibs-table">

                <thead>

                <tr>

                    <th>Employee ID</th>
                    <th>Teacher</th>
                    <th>Username</th>
                    <th>Qualification</th>
                    <th>Specialization</th>
                    <th>Status</th>
                    <th>Action</th>

                </tr>

                </thead>

                <tbody>

                <?php foreach ($teachers as $teacher): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $teacher["employee_id"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $teacher["full_name"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $teacher["username"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $teacher["qualification"] ?? "-"
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $teacher["specialization"] ?? "-"
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $teacher["status"]
                            ) ?>
                        </td>

                        <td>

    <td>

    <a
        href="teacher_assign.php?id=<?= $teacher["id"] ?>"
        class="btn btn-gold"
    >
        Assign
    </a>

    <a
        href="teachers.php?delete=<?= $teacher["id"] ?>"
        class="btn btn-danger"
        onclick="return confirm('Delete this teacher?')"
    >
        Delete
    </a>

</td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$teachers): ?>

                    <tr>

                        <td colspan="7">
                            No teachers have been registered.
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
