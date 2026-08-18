<?php

session_start();

require_once "../config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| DATABASE HEALTH CHECK
|--------------------------------------------------------------------------
*/


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
| REQUIRED TABLES
|--------------------------------------------------------------------------
*/

$requiredTables = [

    "students" => [

        "id",
        "student_id",
        "first_name",
        "middle_name",
        "last_name",
        "gender",
        "dob",
        "photo"

    ],

    "classes" => [

        "id",
        "class_name"

    ],

    "subjects" => [

        "id",
        "subject_name"

    ],

    "academic_years" => [

        "id",
        "academic_year"

    ],

    "terms" => [

        "id",
        "term_name",
        "academic_year_id"

    ],

    "report_card_records" => [

        "id",
        "student_id",
        "class_id",
        "term_id",
        "average_score",
        "position",
        "class_size",
        "days_opened",
        "days_present",
        "days_absent",
        "conduct",
        "promotion_status",
        "teacher_comment",
        "headteacher_comment",
        "report_status",
        "published_at",
        "created_at",
        "updated_at"

    ],

    "report_card_results" => [

        "id",
        "report_id",
        "subject_id",
        "score",
        "grade",
        "comment",
        "created_at",
        "updated_at"

    ]

];


/*
|--------------------------------------------------------------------------
| RESULTS
|--------------------------------------------------------------------------
*/

$tableResults = [];

$totalTables = count(
    $requiredTables
);

$existingTables = 0;

$missingTables = 0;

$totalColumns = 0;

$existingColumns = 0;

$missingColumns = 0;


foreach (
    $requiredTables
    as $table => $columns
) {

    $exists = false;

    $actualColumns = [];

    try {

        $stmt =
            $conn->prepare(
                "SHOW TABLES LIKE ?"
            );

        $stmt->execute([
            $table
        ]);

        $exists =
            $stmt->fetchColumn()
            !== false;


        if ($exists) {

            $existingTables++;


            $stmt =
                $conn->query(
                    "SHOW COLUMNS FROM `"
                    . str_replace(
                        "`",
                        "",
                        $table
                    )
                    . "`"
                );


            while (
                $row =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                )
            ) {

                $actualColumns[] =
                    $row["Field"];
            }

        } else {

            $missingTables++;
        }


    } catch (
        Throwable $e
    ) {

        $exists = false;

        $missingTables++;
    }


    $columnResults = [];


    foreach (
        $columns
        as $column
    ) {

        $totalColumns++;


        $columnExists =
            in_array(
                $column,
                $actualColumns,
                true
            );


        if (
            $columnExists
        ) {

            $existingColumns++;

        } else {

            $missingColumns++;
        }


        $columnResults[
            $column
        ] =
            $columnExists;
    }


    $tableResults[
        $table
    ] = [

        "exists" =>
            $exists,

        "columns" =>
            $columnResults,

        "actual_columns" =>
            $actualColumns

    ];
}


/*
|--------------------------------------------------------------------------
| OVERALL STATUS
|--------------------------------------------------------------------------
*/

$systemReady =
    $missingTables === 0 &&
    $missingColumns === 0;


/*
|--------------------------------------------------------------------------
| DATABASE NAME
|--------------------------------------------------------------------------
*/

$databaseName =
    "Unknown";


try {

    $databaseName =
        $conn
            ->query(
                "SELECT DATABASE()"
            )
            ->fetchColumn();

} catch (
    Throwable $e
) {
}


/*
|--------------------------------------------------------------------------
| SERVER
|--------------------------------------------------------------------------
*/

$mysqlVersion =
    "Unknown";


try {

    $mysqlVersion =
        $conn
            ->query(
                "SELECT VERSION()"
            )
            ->fetchColumn();

} catch (
    Throwable $e
) {
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
    HIBS Reports | Database Health Check
</title>


<style>

/* =========================================================
   BASE
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

    padding: 28px 18px;

    background: #263238;

    color: #ffffff;

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


/* =========================================================
   CONTENT
========================================================= */

.content {

    max-width: 1300px;

    padding:
        30px 35px;

}


/* =========================================================
   HEADER
========================================================= */

.page-header {

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
   DATABASE INFO
========================================================= */

.database-info {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 20px;

}


.info-card {

    padding: 18px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.info-label {

    color: #7a858a;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

}


.info-value {

    margin-top: 7px;

    color: #37474f;

    font-size: 14px;

    font-weight: 600;

    word-break: break-word;

}


/* =========================================================
   SYSTEM STATUS
========================================================= */

.system-status {

    padding: 18px 20px;

    margin-bottom: 20px;

    border:
        1px solid
        #deddd8;

    background: #ffffff;

}


.system-status.ready {

    border-left:
        4px solid
        #657d70;

}


.system-status.not-ready {

    border-left:
        4px solid
        #a45e5e;

}


.status-title {

    font-size: 13px;

    font-weight: 700;

}


.status-text {

    margin-top: 6px;

    color: #7a858a;

    font-size: 9px;

    line-height: 1.7;

}


/* =========================================================
   TABLE
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

    font-size: 15px;

    font-weight: 600;

}


.panel-header p {

    margin:
        6px 0 0;

    color: #899398;

    font-size: 9px;

}


.table-wrap {

    overflow-x: auto;

}


table {

    width: 100%;

    min-width: 800px;

    border-collapse: collapse;

}


thead th {

    padding:
        11px 12px;

    background: #f2f3f1;

    border-bottom:
        1px solid
        #dddcd7;

    color: #69777c;

    font-size: 7px;

    text-align: left;

    text-transform: uppercase;

}


tbody td {

    padding:
        11px 12px;

    border-bottom:
        1px solid
        #eceae6;

    color: #455a64;

    font-size: 9px;

    vertical-align: top;

}


.table-name {

    font-weight: 600;

    color: #37474f;

}


.badge {

    display: inline-block;

    padding:
        5px 8px;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.badge-ok {

    background: #e8f1eb;

    color: #3e6b4e;

}


.badge-missing {

    background: #fbefef;

    color: #8b4b4b;

}


/* =========================================================
   COLUMNS
========================================================= */

.columns {

    display: flex;

    flex-wrap: wrap;

    gap: 5px;

}


.column {

    display: inline-block;

    padding:
        5px 7px;

    background: #eef0ef;

    color: #617077;

    font-size: 7px;

    border-radius: 2px;

}


.column.missing {

    background: #f8e9e9;

    color: #985454;

}


/* =========================================================
   FOOTER
========================================================= */

.footer-note {

    margin-top: 20px;

    color: #8a9498;

    font-size: 8px;

    line-height: 1.7;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:800px) {

    .database-info {

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
        class="nav-link"
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
        href="database_check.php"
        class="nav-link active"
    >
        Database Check
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
            Database Health Check
        </div>

    </header>


    <main class="content">


        <!-- =================================================
             HEADER
        ================================================== -->

        <section class="page-header">

            <h1>
                HIBS Database Health Check
            </h1>

            <p>
                Verify that the database structure required
                by the reporting system is available.
            </p>

        </section>


        <!-- =================================================
             DATABASE INFORMATION
        ================================================== -->

        <section class="database-info">


            <div class="info-card">

                <div class="info-label">
                    Database
                </div>

                <div class="info-value">
                    <?= h(
                        $databaseName
                    ) ?>
                </div>

            </div>


            <div class="info-card">

                <div class="info-label">
                    MySQL Version
                </div>

                <div class="info-value">
                    <?= h(
                        $mysqlVersion
                    ) ?>
                </div>

            </div>


            <div class="info-card">

                <div class="info-label">
                    Required Tables
                </div>

                <div class="info-value">

                    <?= $existingTables ?>

                    /

                    <?= $totalTables ?>

                </div>

            </div>


        </section>


        <!-- =================================================
             SYSTEM STATUS
        ================================================== -->

        <section
            class="
                system-status
                <?= $systemReady
                    ? "ready"
                    : "not-ready"
                ?>
        ">


            <?php if (
                $systemReady
            ): ?>


                <div class="status-title">

                    ✓ HIBS REPORTING DATABASE READY

                </div>


                <div class="status-text">

                    All required reporting tables and
                    columns were found. We can safely move
                    forward with the advanced reporting
                    features.

                </div>


            <?php else: ?>


                <div class="status-title">

                    ⚠ DATABASE STRUCTURE NEEDS ATTENTION

                </div>


                <div class="status-text">

                    Some required tables or columns are
                    missing. Do not continue with the
                    advanced reporting stages until the
                    missing database components have been
                    resolved.

                    <br><br>

                    Missing tables:

                    <strong>
                        <?= $missingTables ?>
                    </strong>

                    &nbsp;&nbsp;

                    Missing columns:

                    <strong>
                        <?= $missingColumns ?>
                    </strong>

                </div>


            <?php endif; ?>


        </section>


        <!-- =================================================
             TABLE CHECK
        ================================================== -->

        <section class="panel">


            <div class="panel-header">

                <h2>
                    Required Database Structure
                </h2>


                <p>

                    The system checks the tables and columns
                    used by the HIBS reporting workflow.

                </p>

            </div>


            <div class="table-wrap">


                <table>


                    <thead>

                    <tr>

                        <th>
                            Table
                        </th>

                        <th>
                            Table Status
                        </th>

                        <th>
                            Required Columns
                        </th>

                    </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $tableResults
                        as $table =>
                        $result
                    ): ?>


                        <tr>


                            <td>

                                <div class="table-name">

                                    <?= h(
                                        $table
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <?php if (
                                    $result["exists"]
                                ): ?>

                                    <span
                                        class="
                                            badge
                                            badge-ok
                                        "
                                    >
                                        Exists
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            badge
                                            badge-missing
                                        "
                                    >
                                        Missing
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>


                                <div class="columns">


                                    <?php foreach (
                                        $result[
                                            "columns"
                                        ]
                                        as $column =>
                                        $exists
                                    ): ?>


                                        <span
                                            class="
                                                column
                                                <?= $exists
                                                    ? ""
                                                    : "missing"
                                                ?>
                                            "
                                        >

                                            <?= h(
                                                $column
                                            ) ?>

                                            <?= $exists
                                                ? "✓"
                                                : "✕"
                                            ?>

                                        </span>


                                    <?php endforeach; ?>


                                </div>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        </section>


        <div class="footer-note">

            This page is diagnostic only. It does not create,
            alter or delete database tables or records.

        </div>


    </main>


</div>


</body>

</html>
