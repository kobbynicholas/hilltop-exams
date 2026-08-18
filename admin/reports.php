<?php

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTING CONTROL CENTRE
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
| ADMIN SECURITY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {

    header(
        "Location: ../login.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$academicYearId =
    filter_input(
        INPUT_GET,
        "academic_year_id",
        FILTER_VALIDATE_INT
    );

$termId =
    filter_input(
        INPUT_GET,
        "term_id",
        FILTER_VALIDATE_INT
    );

$classId =
    filter_input(
        INPUT_GET,
        "class_id",
        FILTER_VALIDATE_INT
    );

$statusFilter =
    trim(
        $_GET["status"] ?? ""
    );


/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$academicYears = [];
$terms = [];
$classes = [];
$reports = [];

$error = "";


/*
|--------------------------------------------------------------------------
| LOAD FILTER DATA
|--------------------------------------------------------------------------
*/

try {


    /*
    |--------------------------------------------------------------------------
    | ACADEMIC YEARS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->query("
        SELECT

            id,
            academic_year

        FROM academic_years

        ORDER BY id DESC
    ");

    $academicYears =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | TERMS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->query("
        SELECT

            id,
            term_name,
            academic_year_id

        FROM terms

        ORDER BY id DESC
    ");

    $terms =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | CLASSES
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->query("
        SELECT

            id,
            class_name

        FROM classes

        ORDER BY class_name ASC
    ");

    $classes =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | REPORT QUERY
    |--------------------------------------------------------------------------
    */

    $sql = "
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

            r.created_at,
            r.updated_at,
            r.published_at,

            s.student_id AS student_number,

            s.first_name,
            s.middle_name,
            s.last_name,

            c.class_name,

            t.term_name,

            ay.academic_year

        FROM report_card_records r

        INNER JOIN students s
            ON s.id = r.student_id

        INNER JOIN classes c
            ON c.id = r.class_id

        INNER JOIN terms t
            ON t.id = r.term_id

        INNER JOIN academic_years ay
            ON ay.id = t.academic_year_id

        WHERE 1 = 1
    ";


    $params = [];


    /*
    |--------------------------------------------------------------------------
    | ACADEMIC YEAR FILTER
    |--------------------------------------------------------------------------
    */

    if (
        $academicYearId
    ) {

        $sql .= "
            AND t.academic_year_id = ?
        ";

        $params[] =
            $academicYearId;
    }


    /*
    |--------------------------------------------------------------------------
    | TERM FILTER
    |--------------------------------------------------------------------------
    */

    if (
        $termId
    ) {

        $sql .= "
            AND r.term_id = ?
        ";

        $params[] =
            $termId;
    }


    /*
    |--------------------------------------------------------------------------
    | CLASS FILTER
    |--------------------------------------------------------------------------
    */

    if (
        $classId
    ) {

        $sql .= "
            AND r.class_id = ?
        ";

        $params[] =
            $classId;
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS FILTER
    |--------------------------------------------------------------------------
    */

    $allowedStatuses = [
        "Draft",
        "Approved",
        "Published"
    ];


    if (
        in_array(
            $statusFilter,
            $allowedStatuses,
            true
        )
    ) {

        $sql .= "
            AND r.report_status = ?
        ";

        $params[] =
            $statusFilter;
    }


    /*
    |--------------------------------------------------------------------------
    | ORDER
    |--------------------------------------------------------------------------
    */

    $sql .= "

        ORDER BY

            ay.id DESC,

            t.id DESC,

            c.class_name ASC,

            s.last_name ASC,

            s.first_name ASC

    ";


    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    $stmt =
        $conn->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );

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
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalReports = count(
    $reports
);

$draftCount = 0;
$approvedCount = 0;
$publishedCount = 0;


foreach (
    $reports
    as $report
) {

    switch (
        $report["report_status"]
    ) {

        case "Draft":

            $draftCount++;

            break;


        case "Approved":

            $approvedCount++;

            break;


        case "Published":

            $publishedCount++;

            break;

    }
}


/*
|--------------------------------------------------------------------------
| ATTENTION COUNT
|--------------------------------------------------------------------------
*/

$attentionCount =
    $draftCount;


/*
|--------------------------------------------------------------------------
| ADMIN NAME
|--------------------------------------------------------------------------
*/

$adminName =
    $_SESSION["name"]
    ??
    $_SESSION["username"]
    ??
    "Administrator";

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
    HIBS Reports | Reporting Control Centre
</title>


<style>

/* =========================================================
   HIBS ADMIN REPORTING CENTRE
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

    color: #fff;

    padding: 28px 18px;

    z-index: 100;

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

    color: #fff;

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

    color: #fff;

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

    border:
        1px solid
        rgba(255,255,255,.15);

    color: #dce2e5;

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

    background: #fff;

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


.admin-name {

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

    max-width: 1600px;

}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    align-items: flex-start;

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

    font-size: 11px;

}


.view-all {

    padding:
        10px 14px;

    background: #455a64;

    color: #fff;

    text-decoration: none;

    font-size: 9px;

    font-weight: bold;

    border-radius: 3px;

}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 25px;

}


.stat-card {

    background: #fff;

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

    margin-top: 8px;

    color: #37474f;

    font-size: 25px;

    font-weight: 600;

}


.stat-note {

    margin-top: 5px;

    color: #9aa2a5;

    font-size: 8px;

}


/* =========================================================
   FILTER PANEL
========================================================= */

.filter-panel {

    background: #fff;

    border:
        1px solid
        #deddd8;

    padding: 20px;

    margin-bottom: 20px;

}


.filter-title {

    margin-bottom: 15px;

    color: #455a64;

    font-size: 11px;

    font-weight: bold;

    text-transform: uppercase;

}


.filters {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr)
        auto;

    gap: 10px;

    align-items: end;

}


.field label {

    display: block;

    margin-bottom: 6px;

    color: #7a858a;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

}


.field select {

    width: 100%;

    height: 38px;

    padding:
        0 10px;

    background: #fff;

    border:
        1px solid
        #d4d3ce;

    color: #455a64;

    font-size: 10px;

    outline: none;

}


.field select:focus {

    border-color:
        #78909c;

}


.filter-button {

    height: 38px;

    padding:
        0 18px;

    border: 0;

    background: #455a64;

    color: #fff;

    font-size: 9px;

    font-weight: bold;

    cursor: pointer;

    border-radius: 3px;

}


.clear-button {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    height: 38px;

    padding:
        0 13px;

    border:
        1px solid
        #d4d3ce;

    color: #68757a;

    background: #fff;

    text-decoration: none;

    font-size: 9px;

    border-radius: 3px;

}


/* =========================================================
   REPORT TABLE
========================================================= */

.panel {

    background: #fff;

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

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.panel-header h2 {

    margin: 0;

    color: #37474f;

    font-size: 15px;

    font-weight: 600;

}


.report-count {

    color: #8a9498;

    font-size: 9px;

}


.table-wrap {

    width: 100%;

    overflow-x: auto;

}


table {

    width: 100%;

    min-width: 1050px;

    border-collapse: collapse;

}


thead th {

    padding:
        11px 10px;

    background: #f2f3f1;

    border-bottom:
        1px solid
        #dddcd7;

    color: #69777c;

    font-size: 7px;

    font-weight: bold;

    text-align: left;

    text-transform: uppercase;

    letter-spacing: .5px;

    white-space: nowrap;

}


tbody td {

    padding:
        12px 10px;

    border-bottom:
        1px solid
        #eceae6;

    color: #455a64;

    font-size: 9px;

    vertical-align: middle;

}


tbody tr:hover {

    background: #fafaf8;

}


.student-name {

    color: #37474f;

    font-weight: 600;

}


.student-id {

    margin-top: 3px;

    color: #9aa2a5;

    font-size: 7px;

}


.average {

    font-weight: 600;

}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding:
        6px 9px;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .4px;

}


.status-draft {

    background: #f3eee5;

    color: #806744;

}


.status-approved {

    background: #e9eef2;

    color: #506675;

}


.status-published {

    background: #e8f1eb;

    color: #3e6b4e;

}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    gap: 5px;

    flex-wrap: wrap;

}


.action {

    display: inline-block;

    padding:
        7px 9px;

    text-decoration: none;

    font-size: 7px;

    font-weight: bold;

    border-radius: 3px;

}


.action-view {

    background: #eef0ef;

    color: #455a64;

}


.action-approve {

    background: #e9eef2;

    color: #506675;

}


.action-publish {

    background: #e8f1eb;

    color: #3e6b4e;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        60px 20px;

    text-align: center;

}


.empty-icon {

    width: 52px;
    height: 52px;

    margin:
        0 auto 13px;

    background: #edf0ef;

    border-radius: 50%;

    color: #78909c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 22px;

}


.empty h3 {

    margin: 0;

    color: #455a64;

    font-size: 15px;

}


.empty p {

    margin:
        7px auto;

    max-width: 450px;

    color: #8a9498;

    font-size: 9px;

    line-height: 1.7;

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

@media(max-width:1100px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .filters {

        grid-template-columns:
            repeat(2, 1fr);

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

    .view-all {

        display: inline-block;

        margin-top: 15px;

    }

}


@media(max-width:500px) {

    .stats {

        grid-template-columns: 1fr;

    }

    .filters {

        grid-template-columns: 1fr;

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
        Administration
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
        Reports
    </a>


    <a
        href="students.php"
        class="nav-link"
    >
        Students
    </a>


    <a
        href="classes.php"
        class="nav-link"
    >
        Classes
    </a>


    <a
        href="subjects.php"
        class="nav-link"
    >
        Subjects
    </a>


    <a
        href="academic_years.php"
        class="nav-link"
    >
        Academic Years
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
            Reporting Control Centre
        </div>


        <div class="admin-name">

            <?= h(
                $adminName
            ) ?>

        </div>


    </header>


    <main class="content">


        <!-- =================================================
             HEADER
        ================================================== -->

        <section class="page-header">


            <div>

                <h1>
                    Academic Reports
                </h1>

                <p>
                    Manage, review and publish official
                    HIBS student reports.
                </p>

            </div>


            <a
                href="dashboard.php"
                class="view-all"
            >
                ← Dashboard
            </a>


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
             STATISTICS
        ================================================== -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    Total Reports
                </div>

                <div class="stat-value">

                    <?= number_format(
                        $totalReports
                    ) ?>

                </div>

                <div class="stat-note">
                    Reports matching current filters
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Draft
                </div>

                <div class="stat-value">

                    <?= number_format(
                        $draftCount
                    ) ?>

                </div>

                <div class="stat-note">
                    Reports requiring attention
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Approved
                </div>

                <div class="stat-value">

                    <?= number_format(
                        $approvedCount
                    ) ?>

                </div>

                <div class="stat-note">
                    Ready for publication
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Published
                </div>

                <div class="stat-value">

                    <?= number_format(
                        $publishedCount
                    ) ?>

                </div>

                <div class="stat-note">
                    Visible to students
                </div>

            </div>


        </section>


        <!-- =================================================
             FILTERS
        ================================================== -->

        <section class="filter-panel">


            <div class="filter-title">
                Report Filters
            </div>


            <form
                method="GET"
            >


                <div class="filters">


                    <!-- ACADEMIC YEAR -->

                    <div class="field">

                        <label>
                            Academic Year
                        </label>

                        <select
                            name="academic_year_id"
                        >

                            <option value="">
                                All Academic Years
                            </option>


                            <?php foreach (
                                $academicYears
                                as $year
                            ): ?>

                                <option
                                    value="<?= (int)$year["id"] ?>"
                                    <?= (
                                        (int)$academicYearId ===
                                        (int)$year["id"]
                                    )
                                        ? "selected"
                                        : ""
                                    ?>
                                >

                                    <?= h(
                                        $year[
                                            "academic_year"
                                        ]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- TERM -->

                    <div class="field">

                        <label>
                            Term
                        </label>

                        <select
                            name="term_id"
                        >

                            <option value="">
                                All Terms
                            </option>


                            <?php foreach (
                                $terms
                                as $term
                            ): ?>

                                <option
                                    value="<?= (int)$term["id"] ?>"
                                    <?= (
                                        (int)$termId ===
                                        (int)$term["id"]
                                    )
                                        ? "selected"
                                        : ""
                                    ?>
                                >

                                    <?= h(
                                        $term[
                                            "term_name"
                                        ]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- CLASS -->

                    <div class="field">

                        <label>
                            Class
                        </label>

                        <select
                            name="class_id"
                        >

                            <option value="">
                                All Classes
                            </option>


                            <?php foreach (
                                $classes
                                as $class
                            ): ?>

                                <option
                                    value="<?= (int)$class["id"] ?>"
                                    <?= (
                                        (int)$classId ===
                                        (int)$class["id"]
                                    )
                                        ? "selected"
                                        : ""
                                    ?>
                                >

                                    <?= h(
                                        $class[
                                            "class_name"
                                        ]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- STATUS -->

                    <div class="field">

                        <label>
                            Status
                        </label>

                        <select
                            name="status"
                        >

                            <option value="">
                                All Statuses
                            </option>

                            <option
                                value="Draft"
                                <?= $statusFilter === "Draft"
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                Draft
                            </option>

                            <option
                                value="Approved"
                                <?= $statusFilter === "Approved"
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                Approved
                            </option>

                            <option
                                value="Published"
                                <?= $statusFilter === "Published"
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                Published
                            </option>

                        </select>

                    </div>


                    <!-- ACTIONS -->

                    <div
                        style="
                            display:flex;
                            gap:7px;
                        "
                    >

                        <button
                            type="submit"
                            class="filter-button"
                        >
                            Filter
                        </button>


                        <a
                            href="reports.php"
                            class="clear-button"
                        >
                            Clear
                        </a>

                    </div>


                </div>


            </form>


        </section>


        <!-- =================================================
             REPORT TABLE
        ================================================== -->

        <section class="panel">


            <div class="panel-header">


                <h2>
                    Report Register
                </h2>


                <div class="report-count">

                    <?= number_format(
                        count($reports)
                    ) ?>

                    report(s)

                </div>


            </div>


            <?php if (
                count($reports) > 0
            ): ?>


                <div class="table-wrap">


                    <table>


                        <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Class
                            </th>

                            <th>
                                Academic Year
                            </th>

                            <th>
                                Term
                            </th>

                            <th>
                                Average
                            </th>

                            <th>
                                Position
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Published
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $reports
                            as $report
                        ): ?>


                            <?php

                            $fullName =
                                trim(
                                    implode(
                                        " ",
                                        array_filter([
                                            $report[
                                                "first_name"
                                            ] ?? "",

                                            $report[
                                                "middle_name"
                                            ] ?? "",

                                            $report[
                                                "last_name"
                                            ] ?? ""
                                        ])
                                    )
                                );


                            $status =
                                $report[
                                    "report_status"
                                ]
                                ?? "Draft";


                            $statusClass =
                                "status-draft";


                            if (
                                $status ===
                                "Approved"
                            ) {

                                $statusClass =
                                    "status-approved";

                            } elseif (
                                $status ===
                                "Published"
                            ) {

                                $statusClass =
                                    "status-published";
                            }

                            ?>


                            <tr>


                                <!-- STUDENT -->

                                <td>

                                    <div
                                        class="student-name"
                                    >

                                        <?= h(
                                            $fullName
                                        ) ?>

                                    </div>


                                    <div
                                        class="student-id"
                                    >

                                        ID:
                                        <?= h(
                                            $report[
                                                "student_number"
                                            ]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- CLASS -->

                                <td>

                                    <?= h(
                                        $report[
                                            "class_name"
                                        ]
                                    ) ?>

                                </td>


                                <!-- YEAR -->

                                <td>

                                    <?= h(
                                        $report[
                                            "academic_year"
                                        ]
                                    ) ?>

                                </td>


                                <!-- TERM -->

                                <td>

                                    <?= h(
                                        $report[
                                            "term_name"
                                        ]
                                    ) ?>

                                </td>


                                <!-- AVERAGE -->

                                <td>

                                    <span
                                        class="average"
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

                                </td>


                                <!-- POSITION -->

                                <td>

                                    <?php if (
                                        $report[
                                            "position"
                                        ] !== null
                                    ): ?>

                                        <?= h(
                                            $report[
                                                "position"
                                            ]
                                        ) ?>

                                        <?php if (
                                            $report[
                                                "class_size"
                                            ] !== null
                                        ): ?>

                                            /
                                            <?= h(
                                                $report[
                                                    "class_size"
                                                ]
                                            ) ?>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="status <?= h(
                                            $statusClass
                                        ) ?>"
                                    >

                                        <?= h(
                                            $status
                                        ) ?>

                                    </span>

                                </td>


                                <!-- PUBLISHED -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $report[
                                                "published_at"
                                            ]
                                        )
                                    ): ?>

                                        <?= h(
                                            date(
                                                "d M Y",
                                                strtotime(
                                                    $report[
                                                        "published_at"
                                                    ]
                                                )
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div
                                        class="actions"
                                    >


                                        <!-- VIEW -->

                                        <a
                                            href="../student_report.php?student_id=<?= (int)$report["student_id"] ?>&class_id=<?= (int)$report["class_id"] ?>&term_id=<?= (int)$report["term_id"] ?>&preview=1"
                                            target="_blank"
                                            rel="noopener"
                                            class="action action-view"
                                        >
                                            View
                                        </a>


                                        <?php if (
                                            $status ===
                                            "Draft"
                                        ): ?>

                                            <a
                                                href="approve_report.php?id=<?= (int)$report["report_id"] ?>"
                                                class="action action-approve"
                                            >
                                                Approve
                                            </a>

                                        <?php endif; ?>


                                        <?php if (
                                            $status ===
                                            "Approved"
                                        ): ?>

                                            <a
                                                href="publish_report.php?id=<?= (int)$report["report_id"] ?>"
                                                class="action action-publish"
                                            >
                                                Publish
                                            </a>

                                        <?php endif; ?>


                                        <?php if (
                                            $status ===
                                            "Published"
                                        ): ?>

                                            <span
                                                class="action action-view"
                                            >
                                                Official
                                            </span>

                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php else: ?>


                <div class="empty">


                    <div class="empty-icon">
                        ▣
                    </div>


                    <h3>
                        No Reports Found
                    </h3>


                    <p>
                        There are no report records matching
                        the selected filters.
                    </p>


                </div>


            <?php endif; ?>


        </section>


    </main>


</div>


</body>

</html>
