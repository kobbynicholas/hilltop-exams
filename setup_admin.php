<?php

require_once "config/db.php";

$full_name = "HIBS Administrator";
$username = "admin";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$role = "admin";

try {

    $check = $conn->prepare(
        "SELECT id FROM users WHERE username = ?"
    );

    $check->execute([$username]);

    if ($check->fetch()) {

        echo "<h2>Admin account already exists.</h2>";
        echo "<p>Username: admin</p>";

    } else {

        $sql = "INSERT INTO users
                (full_name, username, password, role)
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            $full_name,
            $username,
            $password,
            $role
        ]);

        echo "<h2>Admin account created successfully.</h2>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
        echo "<p><a href='login.php'>Go to Login</a></p>";
    }

} catch (PDOException $e) {

    die("Error: " . $e->getMessage());

}
?>
