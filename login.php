<?php

session_start();

require_once "config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter your username and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT *
             FROM users
             WHERE username = ?
             AND status = 'active'
             LIMIT 1"
        );

        $stmt->execute([$username]);

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "admin") {

                header("Location: admin/dashboard.php");
                exit;

            } elseif ($user["role"] === "teacher") {

                header("Location: teacher/dashboard.php");
                exit;

            }

        } else {

            $error = "Invalid username or password.";

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

    <title>HIBS Reports - Login</title>

    <link rel="stylesheet"
          href="assets/css/style.css">

</head>

<body class="login-body">

<div class="login-container">

    <div class="login-logo">

        <div class="school-icon">H</div>

        <h1>HIBS REPORTS</h1>

        <p>Hilltop International British School</p>

    </div>

    <?php if ($error): ?>

        <div class="alert">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="form-group">

            <label>Username</label>

            <input
                type="text"
                name="username"
                placeholder="Enter username"
                required
            >

        </div>

        <div class="form-group">

            <label>Password</label>

            <input
                type="password"
                name="password"
                placeholder="Enter password"
                required
            >

        </div>

        <button type="submit" class="login-btn">
            LOGIN
        </button>

    </form>

    <div class="login-footer">

        HIBS Reports System<br>

        Hilltop International British School

    </div>

</div>

</body>
</html>
