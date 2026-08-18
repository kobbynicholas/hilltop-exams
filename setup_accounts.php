<?php

declare(strict_types=1);

require_once "config/db.php";

ini_set("display_errors", "1");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS ACCOUNT SYSTEM SETUP
|--------------------------------------------------------------------------
*/

$message = "";
$error = "";

try {

    /*
    |--------------------------------------------------------------------------
    | USERS TABLE
    |--------------------------------------------------------------------------
    */

    $conn->exec("
        CREATE TABLE IF NOT EXISTS users (

            id INT UNSIGNED NOT NULL AUTO_INCREMENT,

            username VARCHAR(100) NOT NULL,

            email VARCHAR(150) NULL,

            password VARCHAR(255) NOT NULL,

            role ENUM(
                'admin',
                'teacher',
                'student'
            ) NOT NULL,

            status ENUM(
                'active',
                'inactive'
            ) NOT NULL DEFAULT 'active',

            first_name VARCHAR(100) NULL,

            last_name VARCHAR(100) NULL,

            created_at TIMESTAMP
                NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP
                NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY unique_username (
                username
            ),

            UNIQUE KEY unique_email (
                email
            )

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | CHECK STUDENTS TABLE
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->query("
        SELECT COUNT(*)

        FROM information_schema.columns

        WHERE table_schema = DATABASE()

        AND table_name = 'students'

        AND column_name = 'user_id'
    ");

    $studentUserIdExists =
        (int)$stmt->fetchColumn() > 0;


    if (
        !$studentUserIdExists
    ) {

        $conn->exec("
            ALTER TABLE students

            ADD COLUMN user_id
            INT UNSIGNED NULL

            AFTER id
        ");
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK TEACHERS TABLE
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->query("
        SELECT COUNT(*)

        FROM information_schema.columns

        WHERE table_schema = DATABASE()

        AND table_name = 'teachers'

        AND column_name = 'user_id'
    ");

    $teacherUserIdExists =
        (int)$stmt->fetchColumn() > 0;


    if (
        !$teacherUserIdExists
    ) {

        $conn->exec("
            ALTER TABLE teachers

            ADD COLUMN user_id
            INT UNSIGNED NULL

            AFTER id
        ");
    }


    /*
    |--------------------------------------------------------------------------
    | INDEXES
    |--------------------------------------------------------------------------
    */

    try {

        $conn->exec("
            ALTER TABLE students

            ADD INDEX idx_students_user_id (
                user_id
            )
        ");

    } catch (Throwable $e) {
        // Index may already exist.
    }


    try {

        $conn->exec("
            ALTER TABLE teachers

            ADD INDEX idx_teachers_user_id (
                user_id
            )
        ");

    } catch (Throwable $e) {
        // Index may already exist.
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE ADMIN ACCOUNT IF NONE EXISTS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->query("
        SELECT COUNT(*)

        FROM users

        WHERE role = 'admin'
    ");

    $adminCount =
        (int)$stmt->fetchColumn();


    if (
        $adminCount === 0
    ) {

        $password =
            password_hash(
                "Admin@12345",
                PASSWORD_DEFAULT
            );


        $stmt = $conn->prepare("
            INSERT INTO users
            (
                username,
                email,
                password,
                role,
                status,
                first_name,
                last_name
            )

            VALUES
            (
                ?,
                ?,
                ?,
                'admin',
                'active',
                ?,
                ?
            )
        ");


        $stmt->execute([

            "admin",

            "admin@hibs.edu.gh",

            $password,

            "HIBS",

            "Administrator"

        ]);
    }


    $message =
        "Account system installed successfully.";

} catch (
    Throwable $e
) {

    $error =
        $e->getMessage();
}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>
HIBS Reports Account Setup
</title>

<style>

body {

    margin: 0;

    background: #f4f5f3;

    font-family:
        Arial,
        sans-serif;

}

.box {

    max-width: 650px;

    margin: 80px auto;

    background: white;

    border: 1px solid #ddd;

    padding: 35px;

}

h1 {

    margin-top: 0;

    color: #263238;

}

.success {

    padding: 18px;

    background: #edf5ef;

    color: #477052;

    border: 1px solid #cbdccc;

}

.error {

    padding: 18px;

    background: #faeeee;

    color: #914f4f;

    border: 1px solid #e2cccc;

}

.credentials {

    margin-top: 20px;

    padding: 18px;

    background: #f3f5f4;

    border: 1px solid #ddd;

}

a {

    display: inline-block;

    margin-top: 20px;

    padding: 10px 16px;

    background: #263238;

    color: white;

    text-decoration: none;

}

</style>

</head>

<body>

<div class="box">

<h1>
HIBS Reports Account System
</h1>

<?php if (
    $message !== ""
): ?>

<div class="success">

    <?= htmlspecialchars(
        $message
    ) ?>

</div>


<div class="credentials">

<strong>
Initial Administrator Account
</strong>

<br><br>

Username:

<strong>
admin
</strong>

<br>

Password:

<strong>
Admin@12345
</strong>

<br><br>

Please change this password after logging in.

</div>


<a href="login.php">
    Go to HIBS Login
</a>

<?php endif; ?>


<?php if (
    $error !== ""
): ?>

<div class="error">

<strong>
Setup failed:
</strong>

<br><br>

<?= htmlspecialchars(
    $error
) ?>

</div>

<?php endif; ?>

</div>

</body>

</html>
