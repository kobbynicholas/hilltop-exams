<?php

require_once "config/db.php";

$newPassword = "Admin@12345";

$hashedPassword = password_hash(
    $newPassword,
    PASSWORD_DEFAULT
);

$stmt = $conn->prepare("
    UPDATE users
    SET
        password = ?,
        role = 'admin',
        status = 'active'
    WHERE username = 'admin'
");

$stmt->execute([
    $hashedPassword
]);

if ($stmt->rowCount() > 0) {

    echo "<h2>Administrator account reset successfully.</h2>";

    echo "<p><strong>Username:</strong> admin</p>";

    echo "<p><strong>Temporary Password:</strong> Admin@12345</p>";

    echo "<p>Now go to login.php and sign in.</p>";

    echo "<p style='color:red;'>
        IMPORTANT: Delete reset_admin.php after successfully logging in.
    </p>";

} else {

    echo "<h2>No administrator account was updated.</h2>";

    echo "<p>Make sure the username <strong>admin</strong> exists.</p>";
}
