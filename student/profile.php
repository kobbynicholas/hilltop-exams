<?php

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| STUDENT PROFILE
|--------------------------------------------------------------------------
*/

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| STUDENT AUTHENTICATION
|--------------------------------------------------------------------------
*/

$studentSessionId =
    $_SESSION["student_id"]
    ?? null;


if (
    !$studentSessionId &&
    isset($_SESSION["user_id"]) &&
    (
        ($_SESSION["role"] ?? "") === "student"
    )
) {

    $studentSessionId =
        $_SESSION["user_id"];
}


$studentSessionId =
    filter_var(
        $studentSessionId,
        FILTER_VALIDATE_INT
    );


if (
    !$studentSessionId
) {

    header(
        "Location: ../login.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$student = null;

$error = "";


/*
|--------------------------------------------------------------------------
| LOAD STUDENT
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        SELECT

            s.id,
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,

            s.gender,
            s.dob,
            s.photo,

            s.class_id,

            c.class_name

        FROM students s

        LEFT JOIN classes c
            ON c.id = s.class_id

        WHERE

            s.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $studentSessionId
    ]);

    $student =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$student) {

        throw new Exception(
            "Student profile could not be found."
        );
    }


} catch (Throwable $e) {

    $error =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| STUDENT NAME
|--------------------------------------------------------------------------
*/

$studentName = "";

if ($student) {

    $studentName =
        trim(
            implode(
                " ",
                array_filter([
                    $student["first_name"] ?? "",
                    $student["middle_name"] ?? "",
                    $student["last_name"] ?? ""
                ])
            )
        );
}


/*
|--------------------------------------------------------------------------
| PHOTO
|--------------------------------------------------------------------------
*/

$photoPath = "";

if (
    $student &&
    !empty(
        $student["photo"]
    )
) {

    $possiblePhotos = [

        "../uploads/students/"
        . $student["photo"],

        "../uploads/"
        . $student["photo"],

        "../assets/uploads/students/"
        . $student["photo"]

    ];


    foreach (
        $possiblePhotos
        as $photo
    ) {

        if (
            is_file(
                __DIR__
                . "/"
                . $photo
            )
        ) {

            $photoPath =
                $photo;

            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| INITIAL
|--------------------------------------------------------------------------
*/

$initial = "S";

if (
    !empty(
        $student["first_name"]
    )
) {

    $initial =
        strtoupper(
            substr(
                $student["first_name"],
                0,
                1
            )
        );
}


/*
|--------------------------------------------------------------------------
| DATE OF BIRTH
|--------------------------------------------------------------------------
*/

$formattedDob = "Not provided";

if (
    !empty(
        $student["dob"]
    )
) {

    $timestamp =
        strtotime(
            $student["dob"]
        );

    if ($timestamp) {

        $formattedDob =
            date(
                "d F Y",
                $timestamp
            );
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="theme-color"
    content="#263238"
>

<title>
    HIBS Reports | My Profile
</title>


<style>

/* =========================================================
   HIBS STUDENT PROFILE
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {

    background: #f5f4f0;

    color: #263238;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: 245px;

    height: 100vh;

    background: #263238;

    color: #ffffff;

    padding: 28px 18px;

    z-index: 100;

}


.brand {

    padding:
        4px 10px 28px;

    border-bottom:
        1px solid
        rgba(255,255,255,.12);

    margin-bottom: 22px;

}


.brand-title {

    font-size: 18px;

    font-weight: 700;

    letter-spacing: 1px;

}


.brand-subtitle {

    margin-top: 6px;

    color: #b9c3c8;

    font-size: 9px;

    line-height: 1.5;

    letter-spacing: 1px;

}


.nav-title {

    padding:
        0 10px 8px;

    color: #8e9ba1;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: 1.2px;

}


.nav-link {

    display: block;

    padding: 12px;

    margin-bottom: 4px;

    color: #dce2e5;

    text-decoration: none;

    font-size: 11px;

    border-radius: 4px;

}


.nav-link:hover {

    background: #37474f;

}


.nav-link.active {

    background: #546e7a;

    color: #ffffff;

}


.sidebar-bottom {

    position: absolute;

    left: 18px;
    right: 18px;

    bottom: 22px;

}


.logout {

    display: block;

    padding: 11px;

    color: #dce2e5;

    border:
        1px solid
        rgba(255,255,255,.15);

    text-align: center;

    text-decoration: none;

    font-size: 10px;

    border-radius: 4px;

}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 245px;

    min-height: 100vh;

}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    height: 72px;

    padding:
        0 35px;

    background: #ffffff;

    border-bottom:
        1px solid
        #deddd8;

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.topbar-title {

    color: #263238;

    font-size: 17px;

    font-weight: 600;

}


.topbar-user {

    display: flex;

    align-items: center;

    gap: 10px;

}


.user-avatar {

    width: 35px;
    height: 35px;

    border-radius: 50%;

    background: #78909c;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;

    font-weight: bold;

}


.user-name {

    color: #455a64;

    font-size: 11px;

    font-weight: 600;

}


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding: 30px 35px;

    max-width: 1200px;

}


/* =========================================================
   PAGE INTRO
========================================================= */

.page-intro {

    margin-bottom: 25px;

}


.page-intro h1 {

    margin: 0;

    color: #263238;

    font-size: 25px;

    font-weight: 600;

}


.page-intro p {

    margin:
        7px 0 0;

    color: #7a858a;

    font-size: 12px;

}


/* =========================================================
   PROFILE HERO
========================================================= */

.profile-hero {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    padding: 28px;

    display: flex;

    align-items: center;

    gap: 24px;

    margin-bottom: 20px;

}


.profile-photo {

    width: 105px;
    height: 125px;

    object-fit: cover;

    border:
        1px solid
        #c9c7c2;

}


.photo-placeholder {

    width: 105px;
    height: 125px;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    background: #edf0ef;

    color: #8a9599;

    font-size: 8px;

}


.profile-name {

    margin: 0;

    color: #263238;

    font-size: 23px;

    font-weight: 600;

}


.profile-id {

    margin-top: 8px;

    color: #7a858a;

    font-size: 11px;

}


.profile-status {

    display: inline-block;

    margin-top: 13px;

    padding:
        7px 11px;

    background: #e8f1eb;

    color: #3e6b4e;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

}


/* =========================================================
   INFORMATION GRID
========================================================= */

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;

}


/* =========================================================
   INFORMATION PANEL
========================================================= */

.panel {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.panel-header {

    padding:
        19px 21px;

    border-bottom:
        1px solid
        #e7e5e1;

}


.panel-header h2 {

    margin: 0;

    color: #37474f;

    font-size: 15px;

    font-weight: 600;

}


.panel-header p {

    margin:
        5px 0 0;

    color: #8a9498;

    font-size: 9px;

}


.info-list {

    padding: 8px 21px 15px;

}


.info-row {

    min-height: 55px;

    padding:
        12px 0;

    border-bottom:
        1px solid
        #eceae6;

    display: flex;

    justify-content: space-between;

    gap: 20px;

}


.info-row:last-child {

    border-bottom: 0;

}


.info-label {

    color: #899398;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .5px;

}


.info-value {

    color: #455a64;

    font-size: 10px;

    font-weight: 600;

    text-align: right;

}


/* =========================================================
   ACADEMIC CARD
========================================================= */

.academic-card {

    margin-top: 20px;

}


.academic-content {

    padding: 20px 21px;

}


.class-box {

    padding: 18px;

    background: #f7f7f4;

    border:
        1px solid
        #e5e4df;

}


.class-label {

    color: #899398;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

}


.class-name {

    margin-top: 7px;

    color: #37474f;

    font-size: 21px;

    font-weight: 600;

}


/* =========================================================
   NOTICE
========================================================= */

.notice {

    margin-top: 20px;

    padding: 15px 17px;

    background: #f2f3f2;

    border-left:
        3px solid
        #78909c;

    color: #68757a;

    font-size: 9px;

    line-height: 1.7;

}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    margin-top: 20px;

    display: flex;

    gap: 8px;

}


.btn {

    display: inline-block;

    padding:
        10px 14px;

    text-decoration: none;

    font-size: 9px;

    font-weight: bold;

    border-radius: 3px;

}


.btn-primary {

    background: #455a64;

    color: #ffffff;

}


.btn-primary:hover {

    background: #263238;

}


.btn-secondary {

    background: #ffffff;

    color: #455a64;

    border:
        1px solid
        #ccd1d2;

}


/* =========================================================
   ERROR
========================================================= */

.alert {

    margin-bottom: 20px;

    padding: 13px;

    background: #fbf1f1;

    border:
        1px solid
        #e1c8c8;

    color: #8b4b4b;

    font-size: 10px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:850px) {

    .info-grid {

        grid-template-columns: 1fr;

    }

}


@media(max-width:700px) {

    .sidebar {

        position: static;

        width: 100%;

        height: auto;

        padding: 15px;

    }

    .brand {

        margin-bottom: 12px;

        padding-bottom: 15px;

    }

    .nav-title {

        display: none;

    }

    .nav-link {

        display: inline-block;

        padding: 8px 9px;

        margin-right: 3px;

    }

    .sidebar-bottom {

        position: static;

        margin-top: 15px;

    }

    .main {

        margin-left: 0;

    }

    .topbar {

        padding:
            0 18px;

    }

    .content {

        padding:
            22px 15px;

    }

    .profile-hero {

        align-items: flex-start;

        flex-direction: column;

    }

}


@media(max-width:450px) {

    .info-row {

        display: block;

    }

    .info-value {

        margin-top: 5px;

        text-align: left;

    }

    .topbar-user .user-name {

        display: none;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar">


    <div class="brand">

        <div class="brand-title">
            HIBS REPORTS
        </div>

        <div class="brand-subtitle">
            HILLTOP INTERNATIONAL<br>
            BRITISH SCHOOL
        </div>

    </div>


    <div class="nav-title">
        Student Portal
    </div>


    <a
        href="dashboard.php"
        class="nav-link"
    >
        Dashboard
    </a>


    <a
        href="reports.php"
        class="nav-link"
    >
        My Reports
    </a>


    <a
        href="profile.php"
        class="nav-link active"
    >
        My Profile
    </a>


    <a
        href="schedule.php"
        class="nav-link"
    >
        My Schedule
    </a>


    <div class="sidebar-bottom">

        <a
            href="../logout.php"
            class="logout"
        >
            Sign Out
        </a>

    </div>


</aside>


<!-- =====================================================
     MAIN
====================================================== -->

<div class="main">


    <header class="topbar">


        <div class="topbar-title">
            My Profile
        </div>


        <div class="topbar-user">


            <div class="user-avatar">

                <?= h(
                    $initial
                ) ?>

            </div>


            <div class="user-name">

                <?= h(
                    $studentName
                    ?: "Student"
                ) ?>

            </div>


        </div>


    </header>


    <main class="content">


        <?php if (
            $error !== ""
        ): ?>

            <div class="alert">

                <?= h(
                    $error
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (
            $student
        ): ?>


            <!-- =================================================
                 INTRO
            ================================================== -->

            <section class="page-intro">

                <h1>
                    Student Profile
                </h1>

                <p>
                    Your official HIBS student information.
                </p>

            </section>


            <!-- =================================================
                 PROFILE HERO
            ================================================== -->

            <section class="profile-hero">


                <?php if (
                    $photoPath !== ""
                ): ?>

                    <img
                        src="<?= h(
                            $photoPath
                        ) ?>"
                        class="profile-photo"
                        alt="Student photograph"
                    >

                <?php else: ?>

                    <div class="photo-placeholder">

                        STUDENT<br>
                        PHOTO

                    </div>

                <?php endif; ?>


                <div>

                    <h2 class="profile-name">

                        <?= h(
                            $studentName
                        ) ?>

                    </h2>


                    <div class="profile-id">

                        Student ID:
                        <strong>
                            <?= h(
                                $student["student_id"]
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-status">

                        Active Student

                    </div>

                </div>


            </section>


            <!-- =================================================
                 INFORMATION
            ================================================== -->

            <div class="info-grid">


                <!-- PERSONAL INFORMATION -->

                <section class="panel">


                    <div class="panel-header">

                        <h2>
                            Personal Information
                        </h2>

                        <p>
                            Official information held by HIBS.
                        </p>

                    </div>


                    <div class="info-list">


                        <div class="info-row">

                            <div class="info-label">
                                First Name
                            </div>

                            <div class="info-value">

                                <?= h(
                                    $student["first_name"]
                                ) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">
                                Middle Name
                            </div>

                            <div class="info-value">

                                <?= h(
                                    $student["middle_name"]
                                    ?: "—"
                                ) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">
                                Last Name
                            </div>

                            <div class="info-value">

                                <?= h(
                                    $student["last_name"]
                                ) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">
                                Gender
                            </div>

                            <div class="info-value">

                                <?= h(
                                    $student["gender"]
                                    ?: "—"
                                ) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">
                                Date of Birth
                            </div>

                            <div class="info-value">

                                <?= h(
                                    $formattedDob
                                ) ?>

                            </div>

                        </div>


                    </div>


                </section>


                <!-- ACADEMIC INFORMATION -->

                <section class="panel">


                    <div class="panel-header">

                        <h2>
                            Academic Information
                        </h2>

                        <p>
                            Current student placement.
                        </p>

                    </div>


                    <div class="academic-content">


                        <div class="class-box">

                            <div class="class-label">
                                Current Class
                            </div>

                            <div class="class-name">

                                <?= h(
                                    $student["class_name"]
                                    ?? "Not Assigned"
                                ) ?>

                            </div>

                        </div>


                        <div class="info-list">


                            <div class="info-row">

                                <div class="info-label">
                                    Student ID
                                </div>

                                <div class="info-value">

                                    <?= h(
                                        $student["student_id"]
                                    ) ?>

                                </div>

                            </div>


                            <div class="info-row">

                                <div class="info-label">
                                    Class ID
                                </div>

                                <div class="info-value">

                                    <?= h(
                                        $student["class_id"]
                                        ?? "—"
                                    ) ?>

                                </div>

                            </div>


                            <div class="info-row">

                                <div class="info-label">
                                    Account Status
                                </div>

                                <div class="info-value">

                                    Active

                                </div>

                            </div>


                        </div>


                    </div>


                </section>


            </div>


            <!-- =================================================
                 NOTICE
            ================================================== -->

            <div class="notice">

                Your academic records, marks, attendance,
                grades and official report information are
                managed by HIBS administration. If any
                personal information displayed here is
                incorrect, please contact the school
                administration.

            </div>


            <!-- =================================================
                 ACTIONS
            ================================================== -->

            <div class="actions">


                <a
                    href="dashboard.php"
                    class="btn btn-primary"
                >
                    ← Dashboard
                </a>


                <a
                    href="reports.php"
                    class="btn btn-secondary"
                >
                    View My Reports
                </a>


            </div>


        <?php endif; ?>


    </main>


</div>


</body>

</html>
