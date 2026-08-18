<?php

session_start();

require_once "../config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS STUDENT REPORT CENTRE
|--------------------------------------------------------------------------
*/


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
| STUDENT SECURITY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "student"
) {

    header(
        "Location: ../login.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| STUDENT ID
|--------------------------------------------------------------------------
*/

$studentId =
    (int)$_SESSION["user_id"];


$reports = [];

$error = "";


/*
|--------------------------------------------------------------------------
| LOAD PUBLISHED REPORTS
|--------------------------------------------------------------------------
*/

try {

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

            r.published_at DESC

    ");


    $stmt->execute([
        $studentId
    ]);


    $reports =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    Throwable $e
) {

    $error =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| STUDENT INFORMATION
|--------------------------------------------------------------------------
*/

$student = null;


try {

    $stmt = $conn->prepare("
        SELECT

            student_id,

            first_name,

            middle_name,

            last_name,

            photo,

            class_id

        FROM students

        WHERE id = ?

        LIMIT 1
    ");


    $stmt->execute([
        $studentId
    ]);


    $student =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


} catch (
    Throwable $e
) {

    /*
    | Keep report page functioning even if
    | student profile information fails.
    */
}


$studentName = "Student";


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
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalReports =
    count($reports);


$latestReport =
    $reports[0]
    ?? null;


$latestAverage =
    $latestReport[
        "average_score"
    ]
    ?? null;

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
    HIBS | My Reports
</title>


<style>

/* =========================================================
   RESET
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

    left: 0;
    top: 0;

    width: 245px;

    height: 100vh;

    background: #263238;

    color: #ffffff;

    padding: 28px 18px;

}


.brand {

    padding:
        4px 10px 28px;

    margin-bottom: 22px;

    border-bottom:
        1px solid
        rgba(255,255,255,.12);

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

    letter-spacing: 1px;

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


.student-account {

    color: #546e7a;

    font-size: 10px;

    font-weight: 600;

}


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding:
        30px 35px;

    max-width: 1400px;

}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;

}


.page-header h1 {

    margin: 0;

    color: #263238;

    font-size: 25px;

    font-weight: 600;

}


.page-header p {

    margin:
        7px 0 0;

    color: #7a858a;

    font-size: 10px;

}


/* =========================================================
   PROFILE SUMMARY
========================================================= */

.profile {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    padding: 20px;

    margin-bottom: 22px;

    display: flex;

    align-items: center;

    gap: 17px;

}


.profile-photo {

    width: 65px;
    height: 75px;

    object-fit: cover;

    border:
        1px solid
        #d0cfca;

}


.profile-placeholder {

    width: 65px;
    height: 75px;

    background: #eef0ef;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    color: #899398;

    font-size: 7px;

}


.profile-name {

    margin: 0;

    color: #37474f;

    font-size: 17px;

    font-weight: 600;

}


.profile-id {

    margin-top: 6px;

    color: #899398;

    font-size: 9px;

}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 22px;

}


.stat {

    padding: 18px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.stat-label {

    color: #7a858a;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .5px;

}


.stat-value {

    margin-top: 7px;

    color: #37474f;

    font-size: 22px;

    font-weight: 600;

}


/* =========================================================
   ERROR
========================================================= */

.alert {

    margin-bottom: 20px;

    padding: 13px 15px;

    background: #fbf1f1;

    border:
        1px solid
        #e1c8c8;

    color: #8b4b4b;

    font-size: 9px;

}


/* =========================================================
   REPORT SECTION
========================================================= */

.section {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.section-header {

    padding:
        19px 21px;

    border-bottom:
        1px solid
        #e7e5e1;

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.section-header h2 {

    margin: 0;

    color: #37474f;

    font-size: 15px;

    font-weight: 600;

}


.section-header p {

    margin: 5px 0 0;

    color: #899398;

    font-size: 9px;

}


/* =========================================================
   REPORT GRID
========================================================= */

.report-grid {

    padding: 20px;

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;

}


/* =========================================================
   REPORT CARD
========================================================= */

.report-card {

    border:
        1px solid
        #deddd8;

    background: #ffffff;

    transition:
        box-shadow .15s ease,
        transform .15s ease;

}


.report-card:hover {

    box-shadow:
        0 4px 14px
        rgba(38,50,56,.08);

    transform:
        translateY(-1px);

}


.report-card-top {

    padding: 17px;

    border-bottom:
        1px solid
        #e9e8e4;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

}


.term {

    color: #37474f;

    font-size: 14px;

    font-weight: 600;

}


.year {

    margin-top: 5px;

    color: #899398;

    font-size: 8px;

}


.published {

    margin-top: 5px;

    color: #a0a7a9;

    font-size: 7px;

}


.badge {

    padding:
        6px 9px;

    background: #e8f1eb;

    color: #3e6b4e;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


/* =========================================================
   REPORT INFORMATION
========================================================= */

.report-info {

    padding: 17px;

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 10px;

}


.info {

    padding: 10px;

    background: #f7f7f4;

    border:
        1px solid
        #ecebe7;

    text-align: center;

}


.info-label {

    color: #899398;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.info-value {

    margin-top: 5px;

    color: #455a64;

    font-size: 13px;

    font-weight: 600;

}


/* =========================================================
   REPORT FOOT
========================================================= */

.report-card-footer {

    padding:
        13px 17px;

    background: #fafaf8;

    border-top:
        1px solid
        #e9e8e4;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

}


.class-name {

    color: #7a858a;

    font-size: 8px;

}


.view-button {

    display: inline-block;

    padding:
        9px 12px;

    background: #455a64;

    color: #ffffff;

    text-decoration: none;

    font-size: 8px;

    font-weight: bold;

    border-radius: 3px;

}


.view-button:hover {

    background: #263238;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        65px 20px;

    text-align: center;

}


.empty-icon {

    width: 55px;
    height: 55px;

    margin:
        0 auto 14px;

    background: #edf0ef;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #78909c;

    font-size: 22px;

}


.empty h3 {

    margin: 0;

    color: #455a64;

    font-size: 15px;

}


.empty p {

    max-width: 450px;

    margin:
        8px auto 0;

    color: #899398;

    font-size: 9px;

    line-height: 1.7;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:950px) {

    .report-grid {

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

        padding-bottom: 15px;

        margin-bottom: 12px;

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


    .page-header {

        display: block;

    }


    .stats {

        grid-template-columns: 1fr;

    }

}


@media(max-width:500px) {

    .profile {

        align-items: flex-start;

    }


    .report-info {

        grid-template-columns: 1fr;

    }


    .report-card-top {

        align-items: flex-start;

        flex-direction: column;

    }


    .report-card-footer {

        align-items: stretch;

        flex-direction: column;

    }


    .view-button {

        text-align: center;

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


    <header class="topbar">


        <div class="topbar-title">

            My Academic Reports

        </div>


        <div class="student-account">

            <?= h(
                $studentName
            ) ?>

        </div>


    </header>


    <main class="content">


        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <section class="page-header">


            <div>

                <h1>
                    My Reports
                </h1>

                <p>
                    View your official published academic
                    reports by term.
                </p>

            </div>


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


        <!-- =================================================
             PROFILE
        ================================================== -->

        <section class="profile">


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

                <div class="profile-placeholder">

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


                <?php if (
                    $student
                ): ?>

                    <div class="profile-id">

                        Student ID:

                        <strong>

                            <?= h(
                                $student[
                                    "student_id"
                                ]
                            ) ?>

                        </strong>

                    </div>

                <?php endif; ?>


            </div>


        </section>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <section class="stats">


            <div class="stat">

                <div class="stat-label">

                    Published Reports

                </div>


                <div class="stat-value">

                    <?= number_format(
                        $totalReports
                    ) ?>

                </div>

            </div>


            <div class="stat">

                <div class="stat-label">

                    Latest Average

                </div>


                <div class="stat-value">

                    <?php if (
                        $latestAverage !== null
                    ): ?>

                        <?= number_format(
                            (float)$latestAverage,
                            2
                        ) ?>%

                    <?php else: ?>

                        —

                    <?php endif; ?>

                </div>

            </div>


            <div class="stat">

                <div class="stat-label">

                    Latest Class

                </div>


                <div class="stat-value">

                    <?= h(
                        $latestReport[
                            "class_name"
                        ]
                        ?? "—"
                    ) ?>

                </div>

            </div>


        </section>


        <!-- =================================================
             REPORTS
        ================================================== -->

        <section class="section">


            <div class="section-header">


                <div>

                    <h2>
                        Published Reports
                    </h2>

                    <p>
                        Official reports released by HIBS.
                    </p>

                </div>


                <div>

                    <?= number_format(
                        $totalReports
                    ) ?>

                    report(s)

                </div>


            </div>


            <?php if (
                count($reports) > 0
            ): ?>


                <div class="report-grid">


                    <?php foreach (
                        $reports
                        as $report
                    ): ?>


                        <?php

                        $average =
                            $report[
                                "average_score"
                            ];


                        $position =
                            $report[
                                "position"
                            ];


                        $classSize =
                            $report[
                                "class_size"
                            ];


                        $publishedDate =
                            "—";


                        if (
                            !empty(
                                $report[
                                    "published_at"
                                ]
                            )
                        ) {

                            $timestamp =
                                strtotime(
                                    $report[
                                        "published_at"
                                    ]
                                );


                            if (
                                $timestamp
                            ) {

                                $publishedDate =
                                    date(
                                        "d M Y",
                                        $timestamp
                                    );
                            }
                        }

                        ?>


                        <article
                            class="report-card"
                        >


                            <!-- TOP -->

                            <div class="report-card-top">


                                <div>


                                    <div class="term">

                                        <?= h(
                                            $report[
                                                "term_name"
                                            ]
                                        ) ?>

                                    </div>


                                    <div class="year">

                                        Academic Year:

                                        <?= h(
                                            $report[
                                                "academic_year"
                                            ]
                                        ) ?>

                                    </div>


                                    <div class="published">

                                        Published:

                                        <?= h(
                                            $publishedDate
                                        ) ?>

                                    </div>


                                </div>


                                <span class="badge">

                                    Published

                                </span>


                            </div>


                            <!-- INFORMATION -->

                            <div class="report-info">


                                <div class="info">


                                    <div class="info-label">

                                        Average

                                    </div>


                                    <div class="info-value">

                                        <?php if (
                                            $average
                                            !== null
                                        ): ?>

                                            <?= number_format(
                                                (float)$average,
                                                2
                                            ) ?>%

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </div>


                                </div>


                                <div class="info">


                                    <div class="info-label">

                                        Position

                                    </div>


                                    <div class="info-value">

                                        <?php if (
                                            $position
                                            !== null &&
                                            $position
                                            !== ""
                                        ): ?>

                                            <?= h(
                                                $position
                                            ) ?>

                                            <?php if (
                                                $classSize
                                            ): ?>

                                                /
                                                <?= h(
                                                    $classSize
                                                ) ?>

                                            <?php endif; ?>

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </div>


                                </div>


                                <div class="info">


                                    <div class="info-label">

                                        Conduct

                                    </div>


                                    <div class="info-value">

                                        <?= h(
                                            $report[
                                                "conduct"
                                            ]
                                            ?? "—"
                                        ) ?>

                                    </div>


                                </div>


                            </div>


                            <!-- FOOTER -->

                            <div
                                class="report-card-footer"
                            >


                                <div class="class-name">

                                    Class:

                                    <strong>

                                        <?= h(
                                            $report[
                                                "class_name"
                                            ]
                                        ) ?>

                                    </strong>

                                </div>


                                <a
                                    href="../student_report.php?id=<?= (int)$report["report_id"] ?>"
                                    class="view-button"
                                >
                                    View Official Report
                                </a>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty">


                    <div class="empty-icon">
                        ▣
                    </div>


                    <h3>

                        No Published Reports

                    </h3>


                    <p>

                        Your official academic reports will
                        appear here once HIBS has completed,
                        approved and published them.

                    </p>


                </div>


            <?php endif; ?>


        </section>


    </main>


</div>


</body>

</html>
