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
| GET SETTINGS
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT *
    FROM school_settings
    ORDER BY id
    LIMIT 1
");

$settings = $stmt->fetch();

if (!$settings) {

    $conn->exec("
        INSERT INTO school_settings
        (school_name)
        VALUES
        ('HILLTOP INTERNATIONAL BRITISH SCHOOL')
    ");

    $stmt = $conn->query("
        SELECT *
        FROM school_settings
        ORDER BY id
        LIMIT 1
    ");

    $settings = $stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| SAVE SETTINGS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $school_name =
        trim($_POST["school_name"] ?? "");

    $school_motto =
        trim($_POST["school_motto"] ?? "");

    $address =
        trim($_POST["address"] ?? "");

    $telephone =
        trim($_POST["telephone"] ?? "");

    $email =
        trim($_POST["email"] ?? "");

    $website =
        trim($_POST["website"] ?? "");

    $headteacher_name =
        trim($_POST["headteacher_name"] ?? "");

    $principal_title =
        trim($_POST["principal_title"] ?? "");

    $report_footer =
        trim($_POST["report_footer"] ?? "");


    if ($school_name === "") {

        $error =
            "School name is required.";

    } else {

        $stmt = $conn->prepare("
            UPDATE school_settings
            SET
                school_name = ?,
                school_motto = ?,
                address = ?,
                telephone = ?,
                email = ?,
                website = ?,
                headteacher_name = ?,
                principal_title = ?,
                report_footer = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $school_name,
            $school_motto,
            $address,
            $telephone,
            $email,
            $website,
            $headteacher_name,
            $principal_title,
            $report_footer,
            $settings["id"]
        ]);

        $message =
            "School settings updated successfully.";

        $settings["school_name"] = $school_name;
        $settings["school_motto"] = $school_motto;
        $settings["address"] = $address;
        $settings["telephone"] = $telephone;
        $settings["email"] = $email;
        $settings["website"] = $website;
        $settings["headteacher_name"] =
            $headteacher_name;
        $settings["principal_title"] =
            $principal_title;
        $settings["report_footer"] =
            $report_footer;
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HIBS Reports | Settings</title>

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
                $_SESSION["full_name"]
            ) ?>
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
    <a href="results.php">Results</a>
    <a href="report_cards.php">Report Cards</a>
    <a href="settings.php" class="active">Settings</a>

</nav>


<main class="page">

    <div class="page-heading">

        <div>

            <h2>School Settings</h2>

            <p>
                Configure information displayed on official HIBS reports.
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


    <div class="form-panel">

        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>School Name</label>

                    <input
                        type="text"
                        name="school_name"
                        value="<?= htmlspecialchars(
                            $settings["school_name"]
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>School Motto</label>

                    <input
                        type="text"
                        name="school_motto"
                        value="<?= htmlspecialchars(
                            $settings["school_motto"] ?? ""
                        ) ?>"
                    >

                </div>


                <div class="form-group full">

                    <label>Address</label>

                    <textarea
                        name="address"
                        rows="3"
                    ><?= htmlspecialchars(
                        $settings["address"] ?? ""
                    ) ?></textarea>

                </div>


                <div class="form-group">

                    <label>Telephone</label>

                    <input
                        type="text"
                        name="telephone"
                        value="<?= htmlspecialchars(
                            $settings["telephone"] ?? ""
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Email</label>

                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $settings["email"] ?? ""
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Website</label>

                    <input
                        type="text"
                        name="website"
                        value="<?= htmlspecialchars(
                            $settings["website"] ?? ""
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Headteacher</label>

                    <input
                        type="text"
                        name="headteacher_name"
                        value="<?= htmlspecialchars(
                            $settings["headteacher_name"] ?? ""
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Headteacher Title</label>

                    <input
                        type="text"
                        name="principal_title"
                        value="<?= htmlspecialchars(
                            $settings["principal_title"] ?? "Headteacher"
                        ) ?>"
                    >

                </div>


                <div class="form-group full">

                    <label>Report Footer</label>

                    <textarea
                        name="report_footer"
                        rows="3"
                    ><?= htmlspecialchars(
                        $settings["report_footer"] ?? ""
                    ) ?></textarea>

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Save School Settings
            </button>

        </form>

    </div>

</main>

</body>

</html>
