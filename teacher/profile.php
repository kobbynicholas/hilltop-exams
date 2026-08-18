<?php

session_start();

require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "teacher") {

    header("Location: ../login.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        t.*,
        u.full_name,
        u.username,
        u.status
    FROM teachers t

    INNER JOIN users u
        ON u.id = t.user_id

    WHERE t.user_id = ?

    LIMIT 1
");

$stmt->execute([
    (int)$_SESSION["user_id"]
]);

$teacher = $stmt->fetch();

if (!$teacher) {
    die("Teacher profile not found.");
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | My Profile</title>

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
            <?= htmlspecialchars(
                $teacher["full_name"]
            ) ?>
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

    <a href="classes.php">
        My Classes
    </a>

    <a href="subjects.php">
        My Subjects
    </a>

    <a href="profile.php"
       class="active">
        My Profile
    </a>

</nav>


<main class="page">

    <div class="page-heading">

        <div>

            <h2>
                My Profile
            </h2>

            <p>
                Your HIBS teaching information.
            </p>

        </div>

    </div>


    <div class="content-panel">

        <table class="hibs-table">

            <tbody>

            <tr>

                <th>Full Name</th>

                <td>
                    <?= htmlspecialchars(
                        $teacher["full_name"]
                    ) ?>
                </td>

            </tr>

            <tr>

                <th>Employee ID</th>

                <td>
                    <?= htmlspecialchars(
                        $teacher["employee_id"]
                    ) ?>
                </td>

            </tr>

            <tr>

                <th>Username</th>

                <td>
                    <?= htmlspecialchars(
                        $teacher["username"]
                    ) ?>
                </td>

            </tr>

            <tr>

                <th>Phone</th>

                <td>
                    <?= htmlspecialchars(
                        $teacher["phone"] ?: "-"
                    ) ?>
                </td>

            </tr>

            <tr>

                <th>Qualification</th>

                <td>
                    <?= htmlspecialchars(
                        $teacher["qualification"] ?: "-"
                    ) ?>
                </td>

            </tr>

            <tr>

                <th>Specialization</th>

                <td>
                    <?= htmlspecialchars(
                        $teacher["specialization"] ?: "-"
                    ) ?>
                </td>

            </tr>

            <tr>

                <th>Account Status</th>

                <td>
                    <?= htmlspecialchars(
                        $teacher["status"]
                    ) ?>
                </td>

            </tr>

            </tbody>

        </table>

    </div>

</main>

</body>

</html>
