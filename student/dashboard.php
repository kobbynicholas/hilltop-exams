<?php

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| STUDENT DASHBOARD
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
$latestReport = null;
$reportCount = 0;
$error = "";


/*
|--------------------------------------------------------------------------
| LOAD DASHBOARD DATA
|--------------------------------------------------------------------------
*/

try {


    /*
    |--------------------------------------------------------------------------
    | STUDENT
    |--------------------------------------------------------------------------
    */

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

            c.id AS class_id,
            c.class_name

        FROM students s

        LEFT JOIN classes c
            ON c.id = s.class_id

        WHERE s.id = ?

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
            "Student account could not be found."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REPORT COUNT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_reports

        FROM report_card_records

        WHERE

            student_id = ?

            AND report_status = 'Published'
    ");

    $stmt->execute([
        $studentSessionId
    ]);

    $reportCount =
        (int)(
            $stmt->fetchColumn()
            ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | LATEST PUBLISHED REPORT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT

            r.id AS report_id,

            r.student_id,
            r.class_id,
            r.term_id,

            r.report_status,

            r.average_score,
            r.position,
            r.class_size,

            r.days_opened,
            r.days_present,
            r.days_absent,

            r.conduct,
            r.promotion_status,

            r.published_at,

            c.class_name,

            t.term_name,

            ay.academic_year

        FROM report_card_records r

        INNER JOIN classes c
            ON c.id = r.class_id

        INNER JOIN terms t
            ON t.id = r.term_id

        INNER JOIN academic_years ay
            ON ay.id = t.academic_year_id

        WHERE

            r.student_id = ?

            AND r.report_status = 'Published'

        ORDER BY

            ay.id DESC,
            t.id DESC,
            r.id DESC

        LIMIT 1
    ");

    $stmt->execute([
        $studentSessionId
    ]);

    $latestReport =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


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
| ATTENDANCE
|--------------------------------------------------------------------------
*/

$attendance = 0;

if (
    $latestReport &&
    (int)$latestReport["days_opened"] > 0
) {

    $attendance =
        (
            (int)$latestReport["days_present"]
            /
            (int)$latestReport["days_opened"]
        ) * 100;
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
    HIBS Reports | Student Dashboard
</title>


<style>

/* =========================================================
   HIBS STUDENT DASHBOARD
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
        4px
        10px
        28px;

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
        0
        10px
        8px;

    color: #8e9ba1;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: 1.2px;

}


.nav-link {

    display: block;

    padding:
        12px;

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

    max-width: 1400px;

}


/* =========================================================
   WELCOME
========================================================= */

.welcome {

    margin-bottom: 24px;

}


.welcome h1 {

    margin: 0;

    color: #263238;

    font-size: 25px;

    font-weight: 600;

}


.welcome p {

    margin:
        7px 0 0;

    color: #7a858a;

    font-size: 12px;

}


/* =========================================================
   PROFILE
========================================================= */

.profile-card {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    padding: 22px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;

}


.profile-left {

    display: flex;

    align-items: center;

    gap: 18px;

}


.profile-photo {

    width: 70px;
    height: 83px;

    object-fit: cover;

    border:
        1px solid
        #c9c7c2;

}


.photo-placeholder {

    width: 70px;
    height: 83px;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    background: #edf0ef;

    color: #8a9599;

    font-size: 7px;

}


.profile-name {

    margin: 0;

    color: #263238;

    font-size: 18px;

    font-weight: 600;

}


.profile-info {

    margin-top: 7px;

    color: #7a858a;

    font-size: 10px;

}


.profile-info span {

    margin-right: 15px;

}


.profile-badge {

    padding:
        8px 11px;

    background: #edf2f3;

    color: #546e7a;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

}


/* =========================================================
   STAT CARDS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 25px;

}


.stat-card {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    padding: 19px;

}


.stat-label {

    color: #7a858a;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .7px;

}


.stat-value {

    margin-top: 9px;

    color: #37474f;

    font-size: 24px;

    font-weight: 600;

}


.stat-note {

    margin-top: 5px;

    color: #9aa2a5;

    font-size: 9px;

}


/* =========================================================
   MAIN GRID
========================================================= */

.dashboard-grid {

    display: grid;

    grid-template-columns:
        1.5fr
        1fr;

    gap: 20px;

}


/* =========================================================
   PANEL
========================================================= */

.panel {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.panel-header {

    padding:
        20px 22px;

    border-bottom:
        1px solid
        #e7e5e1;

}


.panel-header h2 {

    margin: 0;

    color: #37474f;

    font-size: 16px;

    font-weight: 600;

}


.panel-header p {

    margin:
        5px 0 0;

    color: #8a9498;

    font-size: 10px;

}


/* =========================================================
   LATEST REPORT
========================================================= */

.latest-body {

    padding: 22px;

}


.term-name {

    color: #37474f;

    font-size: 17px;

    font-weight: 600;

}


.academic-year {

    margin-top: 5px;

    color: #8a9498;

    font-size: 10px;

}


.performance-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;

    margin-top: 20px;

}


.performance-item {

    padding: 13px;

    background: #f7f7f4;

    border:
        1px solid
        #e6e5e0;

}


.performance-label {

    color: #899398;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .5px;

}


.performance-value {

    margin-top: 6px;

    color: #455a64;

    font-size: 17px;

    font-weight: 600;

}


.latest-actions {

    display: flex;

    gap: 8px;

    margin-top: 20px;

}


.btn {

    display: inline-block;

    padding:
        10px 13px;

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

    background: #edf0ef;

    color: #455a64;

}


/* =========================================================
   QUICK LINKS
========================================================= */

.quick-links {

    padding: 10px 20px 20px;

}


.quick-link {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        14px 5px;

    border-bottom:
        1px solid
        #eceae6;

    text-decoration: none;

}


.quick-link:last-child {

    border-bottom: 0;

}


.quick-link-title {

    color: #455a64;

    font-size: 11px;

    font-weight: 600;

}


.quick-link-description {

    margin-top: 4px;

    color: #9aa2a5;

    font-size: 8px;

}


.quick-arrow {

    color: #78909c;

    font-size: 15px;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        45px 20px;

    text-align: center;

}


.empty-icon {

    width: 48px;
    height: 48px;

    margin:
        0 auto 12px;

    border-radius: 50%;

    background: #edf0ef;

    color: #78909c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;

}


.empty h3 {

    margin: 0;

    color: #455a64;

    font-size: 14px;

}


.empty p {

    margin:
        7px auto 0;

    max-width: 330px;

    color: #8a9498;

    font-size: 9px;

    line-height: 1.6;

}


/* =========================================================
   ALERT
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

@media(max-width:1050px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .dashboard-grid {

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

    .profile-card {

        align-items: flex-start;

        flex-direction: column;

    }

}


@media(max-width:500px) {

    .stats {

        grid-template-columns: 1fr;

    }

    .performance-grid {

        grid-template-columns: 1fr;

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
        class="nav-link active"
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
        class="nav-link"
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


    <!-- =================================================
         TOP BAR
    ================================================== -->

    <header class="topbar">

        <div class="topbar-title">
            Student Dashboard
        </div>


        <div class="topbar-user">

            <div class="user-avatar">
                <?= h($initial) ?>
            </div>

            <div class="user-name">
                <?= h(
                    $studentName
                    ?: "Student"
                ) ?>
            </div>

        </div>

    </header>


    <!-- =================================================
         CONTENT
    ================================================== -->

    <main class="content">


        <?php if (
            $error !== ""
        ): ?>

            <div class="alert">

                <?= h($error) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             WELCOME
        ================================================== -->

        <section class="welcome">

            <h1>
                Welcome,
                <?= h(
                    $student["first_name"]
                    ?? "Student"
                ) ?>
            </h1>

            <p>
                Your HIBS academic information and
                published reports are available here.
            </p>

        </section>


        <!-- =================================================
             PROFILE
        ================================================== -->

        <section class="profile-card">


            <div class="profile-left">


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


                    <div class="profile-info">

                        <span>
                            Student ID:
                            <strong>
                                <?= h(
                                    $student["student_id"]
                                ) ?>
                            </strong>
                        </span>


                        <span>
                            Class:
                            <strong>
                                <?= h(
                                    $student["class_name"]
                                    ?? "—"
                                ) ?>
                            </strong>
                        </span>

                    </div>

                </div>


            </div>


            <div class="profile-badge">

                Student Portal

            </div>


        </section>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    Published Reports
                </div>

                <div class="stat-value">

                    <?= number_format(
                        $reportCount
                    ) ?>

                </div>

                <div class="stat-note">
                    Official reports available
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Latest Average
                </div>

                <div class="stat-value">

                    <?php if (
                        $latestReport &&
                        $latestReport[
                            "average_score"
                        ] !== null
                    ): ?>

                        <?= number_format(
                            (float)$latestReport[
                                "average_score"
                            ],
                            2
                        ) ?>%

                    <?php else: ?>

                        —

                    <?php endif; ?>

                </div>

                <div class="stat-note">

                    Latest published result

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Latest Position
                </div>

                <div class="stat-value">

                    <?php if (
                        $latestReport &&
                        $latestReport[
                            "position"
                        ] !== null
                    ): ?>

                        <?= h(
                            $latestReport[
                                "position"
                            ]
                        ) ?>

                        <?php if (
                            $latestReport[
                                "class_size"
                            ] !== null
                        ): ?>

                            /
                            <?= h(
                                $latestReport[
                                    "class_size"
                                ]
                            ) ?>

                        <?php endif; ?>

                    <?php else: ?>

                        —

                    <?php endif; ?>

                </div>

                <div class="stat-note">
                    Latest published term
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Attendance
                </div>

                <div class="stat-value">

                    <?php if (
                        $latestReport
                    ): ?>

                        <?= number_format(
                            $attendance,
                            1
                        ) ?>%

                    <?php else: ?>

                        —

                    <?php endif; ?>

                </div>

                <div class="stat-note">
                    Latest published term
                </div>

            </div>


        </section>


        <!-- =================================================
             DASHBOARD GRID
        ================================================== -->

        <div class="dashboard-grid">


            <!-- =================================================
                 LATEST REPORT
            ================================================== -->

            <section class="panel">


                <div class="panel-header">

                    <h2>
                        Latest Academic Report
                    </h2>

                    <p>
                        Your most recently published
                        official report.
                    </p>

                </div>


                <?php if (
                    $latestReport
                ): ?>


                    <div class="latest-body">


                        <div class="term-name">

                            <?= h(
                                $latestReport[
                                    "term_name"
                                ]
                            ) ?>

                        </div>


                        <div class="academic-year">

                            <?= h(
                                $latestReport[
                                    "academic_year"
                                ]
                            ) ?>

                            &nbsp; • &nbsp;

                            <?= h(
                                $latestReport[
                                    "class_name"
                                ]
                            ) ?>

                        </div>


                        <div class="performance-grid">


                            <div class="performance-item">

                                <div class="performance-label">
                                    Average
                                </div>

                                <div class="performance-value">

                                    <?php if (
                                        $latestReport[
                                            "average_score"
                                        ] !== null
                                    ): ?>

                                        <?= number_format(
                                            (float)$latestReport[
                                                "average_score"
                                            ],
                                            2
                                        ) ?>%

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </div>

                            </div>


                            <div class="performance-item">

                                <div class="performance-label">
                                    Position
                                </div>

                                <div class="performance-value">

                                    <?php if (
                                        $latestReport[
                                            "position"
                                        ] !== null
                                    ): ?>

                                        <?= h(
                                            $latestReport[
                                                "position"
                                            ]
                                        ) ?>

                                        <?php if (
                                            $latestReport[
                                                "class_size"
                                            ] !== null
                                        ): ?>

                                            /
                                            <?= h(
                                                $latestReport[
                                                    "class_size"
                                                ]
                                            ) ?>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </div>

                            </div>


                            <div class="performance-item">

                                <div class="performance-label">
                                    Attendance
                                </div>

                                <div class="performance-value">

                                    <?= number_format(
                                        $attendance,
                                        1
                                    ) ?>%

                                </div>

                            </div>


                            <div class="performance-item">

                                <div class="performance-label">
                                    Promotion
                                </div>

                                <div class="performance-value">

                                    <?= h(
                                        $latestReport[
                                            "promotion_status"
                                        ]
                                        ?? "—"
                                    ) ?>

                                </div>

                            </div>


                        </div>


                        <div class="latest-actions">


                            <a
                                href="../student_report.php?student_id=<?= (int)$student["id"] ?>&class_id=<?= (int)$latestReport["class_id"] ?>&term_id=<?= (int)$latestReport["term_id"] ?>"
                                class="btn btn-primary"
                                target="_blank"
                                rel="noopener"
                            >
                                View Latest Report
                            </a>


                            <a
                                href="reports.php"
                                class="btn btn-secondary"
                            >
                                View All Reports
                            </a>


                        </div>


                    </div>


                <?php else: ?>


                    <div class="empty">


                        <div class="empty-icon">
                            ▣
                        </div>


                        <h3>
                            No Published Report
                        </h3>


                        <p>
                            Your latest academic report
                            will appear here once it has
                            been completed, approved and
                            published by HIBS.
                        </p>


                    </div>


                <?php endif; ?>


            </section>


            <!-- =================================================
                 QUICK LINKS
            ================================================== -->

            <section class="panel">


                <div class="panel-header">

                    <h2>
                        Quick Access
                    </h2>

                    <p>
                        Student portal services.
                    </p>

                </div>


                <div class="quick-links">


                    <a
                        href="reports.php"
                        class="quick-link"
                    >

                        <div>

                            <div class="quick-link-title">
                                My Reports
                            </div>

                            <div class="quick-link-description">
                                View your published academic reports.
                            </div>

                        </div>

                        <div class="quick-arrow">
                            →
                        </div>

                    </a>


                    <a
                        href="profile.php"
                        class="quick-link"
                    >

                        <div>

                            <div class="quick-link-title">
                                My Profile
                            </div>

                            <div class="quick-link-description">
                                View your student information.
                            </div>

                        </div>

                        <div class="quick-arrow">
                            →
                        </div>

                    </a>


                    <a
                        href="schedule.php"
                        class="quick-link"
                    >

                        <div>

                            <div class="quick-link-title">
                                My Schedule
                            </div>

                            <div class="quick-link-description">
                                View your class timetable.
                            </div>

                        </div>

                        <div class="quick-arrow">
                            →
                        </div>

                    </a>


                </div>


            </section>


        </div>


    </main>


</div>


</body>

</html>
