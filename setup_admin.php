<?php

/*
|--------------------------------------------------------------------------
| HIBS REPORTS - FIRST TIME INSTALLER
|--------------------------------------------------------------------------
*/

$host = "localhost";
$dbname = "hibs_reports";
$dbuser = "root";
$dbpass = "";

try {

    // Connect to MySQL server
    $pdo = new PDO(
        "mysql:host=$host;charset=utf8mb4",
        $dbuser,
        $dbpass
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database
    $pdo->exec("
        CREATE DATABASE IF NOT EXISTS `$dbname`
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci
    ");

    // Connect to HIBS database
    $pdo->exec("USE `$dbname`");

    /*
    |--------------------------------------------------------------------------
    | USERS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    /*
    |--------------------------------------------------------------------------
    | CLASSES
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_name VARCHAR(100) NOT NULL,
            class_level VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    /*
    |--------------------------------------------------------------------------
    | STUDENTS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL UNIQUE,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) NOT NULL,
            gender ENUM('Male','Female') NOT NULL,
            date_of_birth DATE DEFAULT NULL,
            class_id INT DEFAULT NULL,
            photo VARCHAR(255) DEFAULT NULL,
            status ENUM('Active','Inactive') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (class_id)
            REFERENCES classes(id)
            ON DELETE SET NULL
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    /*
    |--------------------------------------------------------------------------
    | SUBJECTS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject_code VARCHAR(30) UNIQUE,
            subject_name VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    /*
    |--------------------------------------------------------------------------
    | ACADEMIC YEARS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS academic_years (
            id INT AUTO_INCREMENT PRIMARY KEY,
            academic_year VARCHAR(20) NOT NULL,
            status ENUM('Active','Inactive') DEFAULT 'Inactive',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    /*
    |--------------------------------------------------------------------------
    | TERMS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS terms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            term_name VARCHAR(50) NOT NULL,
            academic_year_id INT NOT NULL,
            status ENUM('Active','Inactive') DEFAULT 'Inactive',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (academic_year_id)
            REFERENCES academic_years(id)
            ON DELETE CASCADE
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    /*
    |--------------------------------------------------------------------------
    | MARKS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            term_id INT NOT NULL,

            classwork DECIMAL(5,2) DEFAULT 0,
            test DECIMAL(5,2) DEFAULT 0,
            examination DECIMAL(5,2) DEFAULT 0,

            total DECIMAL(5,2) DEFAULT 0,
            grade VARCHAR(10) DEFAULT NULL,
            grade_description VARCHAR(100) DEFAULT NULL,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            UNIQUE KEY unique_mark (
                student_id,
                subject_id,
                term_id
            ),

            FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE CASCADE,

            FOREIGN KEY (subject_id)
                REFERENCES subjects(id)
                ON DELETE CASCADE,

            FOREIGN KEY (term_id)
                REFERENCES terms(id)
                ON DELETE CASCADE

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            term_id INT NOT NULL,

            days_school_opened INT DEFAULT 0,
            days_present INT DEFAULT 0,
            days_absent INT DEFAULT 0,
            days_late INT DEFAULT 0,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY unique_attendance (
                student_id,
                term_id
            ),

            FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE CASCADE,

            FOREIGN KEY (term_id)
                REFERENCES terms(id)
                ON DELETE CASCADE

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    /*
    |--------------------------------------------------------------------------
    | COMMENTS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            term_id INT NOT NULL,

            teacher_comment TEXT DEFAULT NULL,
            head_comment TEXT DEFAULT NULL,
            conduct VARCHAR(100) DEFAULT NULL,
            effort VARCHAR(100) DEFAULT NULL,
            promotion_status VARCHAR(100) DEFAULT NULL,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY unique_comment (
                student_id,
                term_id
            ),

            FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE CASCADE,

            FOREIGN KEY (term_id)
                REFERENCES terms(id)
                ON DELETE CASCADE

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    /*
    |--------------------------------------------------------------------------
    | SCHOOL SETTINGS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS school_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_name VARCHAR(200) NOT NULL,
            address TEXT DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(150) DEFAULT NULL,
            logo VARCHAR(255) DEFAULT NULL,
            motto VARCHAR(255) DEFAULT NULL,
            principal_name VARCHAR(150) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    /*
    |--------------------------------------------------------------------------
    | INSERT SCHOOL INFORMATION
    |--------------------------------------------------------------------------
    */

    $checkSchool = $pdo->query(
        "SELECT COUNT(*) FROM school_settings"
    )->fetchColumn();

    if ($checkSchool == 0) {

        $stmt = $pdo->prepare("
            INSERT INTO school_settings
            (school_name, address, motto)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            "Hilltop International British School",
            "Kumasi, Ghana",
            "Excellence in Education"
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT SUBJECTS
    |--------------------------------------------------------------------------
    */

    $subjects = [
        ["ENG", "English Language"],
        ["MATH", "Mathematics"],
        ["SCI", "Science"],
        ["BIO", "Biology"],
        ["CHEM", "Chemistry"],
        ["PHY", "Physics"],
        ["ICT", "Information and Communication Technology"],
        ["GEO", "Geography"],
        ["HIST", "History"],
        ["BUS", "Business Studies"],
        ["ECON", "Economics"]
    ];

    $subjectStmt = $pdo->prepare("
        INSERT IGNORE INTO subjects
        (subject_code, subject_name)
        VALUES (?, ?)
    ");

    foreach ($subjects as $subject) {
        $subjectStmt->execute($subject);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE ADMIN
    |--------------------------------------------------------------------------
    */

    $adminCheck = $pdo->prepare("
        SELECT id
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $adminCheck->execute(["admin"]);

    if (!$adminCheck->fetch()) {

        $password = password_hash(
            "admin123",
            PASSWORD_DEFAULT
        );

        $adminStmt = $pdo->prepare("
            INSERT INTO users
            (full_name, username, password, role, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        $adminStmt->execute([
            "HIBS Administrator",
            "admin",
            $password,
            "admin",
            "active"
        ]);

        $adminCreated = true;

    } else {

        $adminCreated = false;
    }

    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta name="viewport"
              content="width=device-width, initial-scale=1.0">

        <title>HIBS Reports Installation</title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #f3f6fb;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }

            .box {
                width: 520px;
                max-width: 92%;
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 35px rgba(0,0,0,.12);
            }

            .logo {
                width: 70px;
                height: 70px;
                border-radius: 50%;
                background: #071d49;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: auto;
                font-size: 32px;
                font-weight: bold;
            }

            h1 {
                text-align: center;
                color: #071d49;
                margin-bottom: 5px;
            }

            .school {
                text-align: center;
                color: #666;
                margin-bottom: 30px;
            }

            .success {
                background: #e8f8ee;
                border: 1px solid #a9dfbd;
                color: #176b38;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
            }

            .credentials {
                background: #f5f7fa;
                padding: 20px;
                border-radius: 8px;
                margin-top: 20px;
            }

            .credentials strong {
                color: #071d49;
            }

            .button {
                display: block;
                text-align: center;
                background: #071d49;
                color: white;
                text-decoration: none;
                padding: 14px;
                border-radius: 8px;
                margin-top: 25px;
            }

            .warning {
                margin-top: 20px;
                background: #fff4df;
                border: 1px solid #f0cf8b;
                color: #775500;
                padding: 12px;
                border-radius: 8px;
                font-size: 14px;
            }

        </style>

    </head>

    <body>

    <div class="box">

        <div class="logo">H</div>

        <h1>HIBS REPORTS</h1>

        <div class="school">
            Hilltop International British School
        </div>

        <div class="success">

            <strong>Installation successful!</strong>

            <br><br>

            The HIBS Reports database and required tables
            have been created successfully.

        </div>

        <div class="credentials">

            <strong>Administrator Login</strong>

            <br><br>

            Username:
            <strong>admin</strong>

            <br>

            Password:
            <strong>admin123</strong>

        </div>

        <a class="button" href="login.php">
            Go to HIBS Reports Login
        </a>

        <div class="warning">

            <strong>Security:</strong>

            After confirming that the system works,
            delete <strong>setup_admin.php</strong>
            from the HIBS Reports folder.

        </div>

    </div>

    </body>

    </html>

    <?php

} catch (PDOException $e) {

    echo "<h2>Installation Error</h2>";

    echo "<p>";

    echo htmlspecialchars($e->getMessage());

    echo "</p>";

}
?>
