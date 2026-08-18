<?php

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| STUDENT - MY REPORTS
|--------------------------------------------------------------------------
|
| Students can only view their own PUBLISHED reports.
|
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
|
| The page supports the common HIBS student session names.
|
*/

$studentSessionId =
    $_SESSION["student_id"]
    ?? null;


/*
|--------------------------------------------------------------------------
| FALLBACK
|--------------------------------------------------------------------------
|
| If the student login stores the student's database
| ID as user_id, allow it when the role is student.
|
*/

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


/*
|--------------------------------------------------------------------------
| AUTHENTICATION CHECK
|--------------------------------------------------------------------------
*/

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
| VALIDATE ID
|--------------------------------------------------------------------------
*/

$studentSessionId =
    filter_var(
        $studentSessionId,
        FILTER_VALIDATE_INT
    );


if (
    !$studentSessionId
) {

    session_destroy();

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
$reports = [];

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
            s.photo,

            s.class_id,

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
    | LOAD PUBLISHED REPORTS
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
    ");

    $stmt->execute([
        $studentSessionId
    ]);

    $reports =
        $stmt->fetchAll(
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
| LATEST REPORT
|--------------------------------------------------------------------------
*/

$latestReport =
    $reports[0]
    ?? null;


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
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitle =
    "My Reports";

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
    content="#334155"
>

<title>
    HIBS Reports | My Reports
</title>


<style>

/* =========================================================
   HIBS STUDENT PORTAL
   DISTINCT FROM NISEL EDUCATION
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
        12px
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
        0
        35px;

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

    margin-bottom: 25px;

}


.welcome h1 {

    margin: 0;

    color: #263238;

    font-size: 25px;

    font-weight: 600;

}


.welcome p {

    margin:
        7px
        0
        0;

    color: #7a858a;

    font-size: 12px;

}


/* =========================================================
   PROFILE CARD
========================================================= */

.profile-card {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    padding: 22px;

    display: flex;

    align-items: center;

    gap: 18px;

    margin-bottom: 22px;

}


.profile-photo {

    width: 65px;
    height: 78px;

    object-fit: cover;

    border:
        1px solid
        #c9c7c2;

}


.photo-placeholder {

    width: 65px;
    height: 78px;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    background: #eef0ef;

    color: #8a9599;

    font-size: 7px;

}


.profile-name {

    margin: 0;

    color: #263238;

    font-size: 16px;

    font-weight: 600;

}


.profile-details {

    margin-top: 6px;

    color: #7a858a;

    font-size: 10px;

}


.profile-details span {

    margin-right: 16px;

}


/* =========================================================
   STAT CARDS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 25px;

}


.stat-card {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    padding: 18px;

}


.stat-label {

    color: #7a858a;

    font-size: 9px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .7px;

}


.stat-number {

    margin-top: 8px;

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
   REPORT PANEL
========================================================= */

.panel {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.panel-header {

    padding:
        20px
        22px;

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
        5px
        0
        0;

    color: #8a9498;

    font-size: 10px;

}


/* =========================================================
   REPORT LIST
========================================================= */

.report-list {

    width: 100%;

}


.report-row {

    padding:
        18px
        22px;

    border-bottom:
        1px solid
        #eceae6;

    display: grid;

    grid-template-columns:
        1.3fr
        1fr
        .8fr
        .8fr
        auto;

    gap: 15px;

    align-items: center;

}


.report-row:last-child {

    border-bottom: 0;

}


.report-row:hover {

    background: #fafaf8;

}


.report-year {

    color: #37474f;

    font-size: 12px;

    font-weight: 600;

}


.report-term {

    margin-top: 4px;

    color: #899398;

    font-size: 9px;

}


.report-label {

    display: block;

    margin-bottom: 4px;

    color: #9aa2a5;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .6px;

}


.report-value {

    color: #455a64;

    font-size: 10px;

}


.status {

    display: inline-block;

    padding:
        6px
        9px;

    background: #e8f1eb;

    color: #3e6b4e;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .4px;

}


.btn-view {

    display: inline-block;

    padding:
        9px
        13px;

    background: #455a64;

    color: #ffffff;

    text-decoration: none;

    font-size: 9px;

    font-weight: bold;

    border-radius: 3px;

}


.btn-view:hover {

    background: #263238;

}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {

    padding:
        65px
        25px;

    text-align: center;

}


.empty-icon {

    width: 55px;
    height: 55px;

    margin:
        0
        auto
        15px;

    background: #edf0ef;

    color: #78909c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;

    border-radius: 50%;

}


.empty h3 {

    margin: 0;

    color: #455a64;

    font-size: 16px;

    font-weight: 600;

}


.empty p {

    max-width: 470px;

    margin:
        8px
        auto
        0;

    color: #8a9498;

    font-size: 10px;

    line-height: 1.7;

}


/* =========================================================
   ALERT
========================================================= */

.alert {

    margin-bottom: 20px;

    padding: 13px 15px;

    border:
        1px solid
        #e1c8c8;

    background: #fbf1f1;

    color: #8b4b4b;

    font-size: 11px;

}


/* =========================================================
   MOBILE
========================================================= */

.mobile-menu {

    display: none;

}


@media(max-width:900px) {

    .sidebar {

        width: 210px;

    }

    .main {

        margin-left: 210px;

    }

    .report-row {

        grid-template-columns:
            1fr
            1fr;

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

    .sidebar-bottom {

        position: static;

        margin-top: 15px;

    }

    .nav-title {

        display: none;

    }

    .nav-link {

        display: inline-block;

        margin-right: 4px;

        padding: 8px 9px;

    }

    .main {

        margin-left: 0;

    }

    .topbar {

        padding:
            0
            18px;

    }

    .content {

        padding:
            22px
            15px;

    }

    .stats {

        grid-template-columns: 1fr;

    }

}


@media(max-width:480px) {

    .profile-card {

        align-items: flex-start;

    }

    .profile-details span {

        display: block;

        margin-bottom: 4px;

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
        class="nav-link active"
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

            My Reports

        </div>


        <div class="topbar-user">


            <div class="user-avatar">

                <?= h(
                    strtoupper(
                        substr(
                            $student["first_name"]
                            ?? "S",
                            0,
                            1
                        )
                    )
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


    <!-- =================================================
         CONTENT
    ================================================== -->

    <main class="content">


        <!-- =================================================
             WELCOME
        ================================================== -->

        <section class="welcome">

            <h1>
                Academic Reports
            </h1>

            <p>
                View your official published HIBS
                academic reports.
            </p>

        </section>


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
                 STUDENT PROFILE
            ================================================== -->

            <section class="profile-card">


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

                    <div
                        class="photo-placeholder"
                    >
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


                    <div class="profile-details">

                        <span>
                            ID:
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


                        <span>
                            Gender:
                            <strong>
                                <?= h(
                                    $student["gender"]
                                    ?? "—"
                                ) ?>
                            </strong>
                        </span>

                    </div>

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

                    <div class="stat-number">

                        <?= number_format(
                            count($reports)
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

                    <div class="stat-number">

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

                        <?php if (
                            $latestReport
                        ): ?>

                            <?= h(
                                $latestReport[
                                    "term_name"
                                ]
                            ) ?>

                        <?php else: ?>

                            No published report

                        <?php endif; ?>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-label">
                        Latest Attendance
                    </div>

                    <div class="stat-number">

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
                 REPORT LIST
            ================================================== -->

            <section class="panel">


                <div class="panel-header">

                    <h2>
                        Published Reports
                    </h2>

                    <p>
                        Only official reports published
                        by HIBS administration are shown.
                    </p>

                </div>


                <?php if (
                    count($reports) > 0
                ): ?>


                    <div class="report-list">


                        <?php foreach (
                            $reports
                            as $report
                        ): ?>


                            <div
                                class="report-row"
                            >


                                <!-- YEAR / TERM -->

                                <div>

                                    <div
                                        class="report-year"
                                    >

                                        <?= h(
                                            $report[
                                                "academic_year"
                                            ]
                                        ) ?>

                                    </div>


                                    <div
                                        class="report-term"
                                    >

                                        <?= h(
                                            $report[
                                                "term_name"
                                            ]
                                        ) ?>

                                    </div>

                                </div>


                                <!-- CLASS -->

                                <div>

                                    <span
                                        class="report-label"
                                    >
                                        Class
                                    </span>

                                    <span
                                        class="report-value"
                                    >

                                        <?= h(
                                            $report[
                                                "class_name"
                                            ]
                                        ) ?>

                                    </span>

                                </div>


                                <!-- AVERAGE -->

                                <div>

                                    <span
                                        class="report-label"
                                    >
                                        Average
                                    </span>

                                    <span
                                        class="report-value"
                                    >

                                        <?php if (
                                            $report[
                                                "average_score"
                                            ] !== null
                                        ): ?>

                                            <?= number_format(
                                                (float)$report[
                                                    "average_score"
                                                ],
                                                2
                                            ) ?>%

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </span>

                                </div>


                                <!-- STATUS -->

                                <div>

                                    <span
                                        class="report-label"
                                    >
                                        Status
                                    </span>

                                    <span
                                        class="status"
                                    >
                                        Published
                                    </span>

                                </div>


                                <!-- ACTION -->

                                <div>

                                    <a
                                        href="../student_report.php?student_id=<?= (int)$student["id"] ?>&class_id=<?= (int)$report["class_id"] ?>&term_id=<?= (int)$report["term_id"] ?>"
                                        class="btn-view"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        View Report
                                    </a>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <div class="empty">


                        <div class="empty-icon">
                            ▣
                        </div>


                        <h3>
                            No Published Reports Yet
                        </h3>


                        <p>
                            Your official academic report
                            will appear here after the HIBS
                            administration has completed,
                            approved and published it.
                        </p>


                    </div>


                <?php endif; ?>


            </section>


        <?php endif; ?>


    </main>


</div>


</body>

</html>
