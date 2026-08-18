<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTS - ADMIN DASHBOARD
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| ADMIN SECURITY
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION["user_id"]) ||
    strtolower((string)($_SESSION["role"] ?? "")) !== "admin"
) {
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| HELPERS
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
| SAFE DATABASE HELPERS
|--------------------------------------------------------------------------
*/

function tableExists(PDO $conn, string $table): bool
{
    try {

        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            AND table_name = ?
        ");

        $stmt->execute([$table]);

        return (int)$stmt->fetchColumn() > 0;

    } catch (Throwable $e) {

        return false;
    }
}


function columnExists(
    PDO $conn,
    string $table,
    string $column
): bool {

    try {

        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
            AND table_name = ?
            AND column_name = ?
        ");

        $stmt->execute([
            $table,
            $column
        ]);

        return (int)$stmt->fetchColumn() > 0;

    } catch (Throwable $e) {

        return false;
    }
}


function tableCount(
    PDO $conn,
    string $table
): int {

    if (!tableExists($conn, $table)) {
        return 0;
    }

    try {

        return (int)$conn
            ->query(
                "SELECT COUNT(*) FROM `$table`"
            )
            ->fetchColumn();

    } catch (Throwable $e) {

        return 0;
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT ADMIN
|--------------------------------------------------------------------------
*/

$adminName = "Administrator";

if (
    tableExists($conn, "users")
) {

    try {

        $stmt = $conn->prepare("
            SELECT
                first_name,
                last_name,
                username,
                email
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            (int)$_SESSION["user_id"]
        ]);

        $admin = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if ($admin) {

            $fullName = trim(
                ($admin["first_name"] ?? "")
                . " "
                . ($admin["last_name"] ?? "")
            );

            if ($fullName !== "") {
                $adminName = $fullName;
            }
            elseif (
                !empty($admin["username"])
            ) {
                $adminName =
                    $admin["username"];
            }
            elseif (
                !empty($admin["email"])
            ) {
                $adminName =
                    $admin["email"];
            }
        }

    } catch (Throwable $e) {
        // Keep default administrator name.
    }
}


/*
|--------------------------------------------------------------------------
| CORE COUNTS
|--------------------------------------------------------------------------
*/

$totalStudents =
    tableCount(
        $conn,
        "students"
    );

$totalTeachers =
    tableCount(
        $conn,
        "teachers"
    );

$totalClasses =
    tableCount(
        $conn,
        "classes"
    );

$totalSubjects =
    tableCount(
        $conn,
        "subjects"
    );

$totalAcademicYears =
    tableCount(
        $conn,
        "academic_years"
    );

$totalTerms =
    tableCount(
        $conn,
        "terms"
    );

$totalReports =
    tableCount(
        $conn,
        "report_card_records"
    );

$totalResults =
    tableCount(
        $conn,
        "report_card_results"
    );

$totalAssignments =
    tableCount(
        $conn,
        "teacher_class_subjects"
    );


/*
|--------------------------------------------------------------------------
| REPORT STATUS COUNTS
|--------------------------------------------------------------------------
*/

$publishedReports = 0;
$pendingReports = 0;
$draftReports = 0;
$returnedReports = 0;

if (
    tableExists(
        $conn,
        "report_card_records"
    )
    &&
    columnExists(
        $conn,
        "report_card_records",
        "report_status"
    )
) {

    try {

        $stmt = $conn->query("
            SELECT
                LOWER(
                    COALESCE(
                        report_status,
                        ''
                    )
                ) AS report_status,
                COUNT(*) AS total

            FROM report_card_records

            GROUP BY report_status
        ");

        $statuses =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        foreach (
            $statuses as $status
        ) {

            $name =
                strtolower(
                    trim(
                        (string)
                        $status[
                            "report_status"
                        ]
                    )
                );

            $count =
                (int)
                $status["total"];


            if (
                $name === "published"
            ) {

                $publishedReports +=
                    $count;

            }
            elseif (
                in_array(
                    $name,
                    [
                        "pending",
                        "submitted",
                        "awaiting approval",
                        "awaiting_approval",
                        "approved"
                    ],
                    true
                )
            ) {

                $pendingReports +=
                    $count;

            }
            elseif (
                in_array(
                    $name,
                    [
                        "draft",
                        "drafted"
                    ],
                    true
                )
            ) {

                $draftReports +=
                    $count;

            }
            elseif (
                in_array(
                    $name,
                    [
                        "returned",
                        "rejected"
                    ],
                    true
                )
            ) {

                $returnedReports +=
                    $count;
            }
        }

    } catch (Throwable $e) {
        // Keep zero values.
    }
}


/*
|--------------------------------------------------------------------------
| MARK SUBMISSIONS
|--------------------------------------------------------------------------
*/

$markSubmissions = 0;
$pendingMarkSubmissions = 0;

if (
    tableExists(
        $conn,
        "mark_submissions"
    )
) {

    $markSubmissions =
        tableCount(
            $conn,
            "mark_submissions"
        );


    if (
        columnExists(
            $conn,
            "mark_submissions",
            "status"
        )
    ) {

        try {

            $stmt = $conn->query("
                SELECT COUNT(*)

                FROM mark_submissions

                WHERE LOWER(
                    COALESCE(
                        status,
                        ''
                    )
                ) IN (
                    'submitted',
                    'pending',
                    'awaiting approval',
                    'awaiting_approval'
                )
            ");

            $pendingMarkSubmissions =
                (int)$stmt->fetchColumn();

        } catch (Throwable $e) {

            $pendingMarkSubmissions = 0;
        }
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT ACADEMIC YEAR
|--------------------------------------------------------------------------
*/

$currentAcademicYear =
    "Not configured";

if (
    tableExists(
        $conn,
        "academic_years"
    )
) {

    try {

        $stmt = $conn->query("
            SELECT academic_year

            FROM academic_years

            ORDER BY id DESC

            LIMIT 1
        ");

        $value =
            $stmt->fetchColumn();

        if ($value) {
            $currentAcademicYear =
                (string)$value;
        }

    } catch (Throwable $e) {
        // Keep default.
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT TERM
|--------------------------------------------------------------------------
*/

$currentTerm =
    "Not configured";

if (
    tableExists(
        $conn,
        "terms"
    )
) {

    try {

        $stmt = $conn->query("
            SELECT term_name

            FROM terms

            ORDER BY id DESC

            LIMIT 1
        ");

        $value =
            $stmt->fetchColumn();

        if ($value) {
            $currentTerm =
                (string)$value;
        }

    } catch (Throwable $e) {
        // Keep default.
    }
}


/*
|--------------------------------------------------------------------------
| RECENT REPORT ACTIVITY
|--------------------------------------------------------------------------
*/

$recentReports = [];

if (
    tableExists(
        $conn,
        "report_card_records"
    )
) {

    try {

        $select = "
            r.id,
            r.student_id,
            r.class_id,
            r.term_id
        ";

        if (
            columnExists(
                $conn,
                "report_card_records",
                "report_status"
            )
        ) {

            $select .= ",
                r.report_status
            ";
        }

        if (
            columnExists(
                $conn,
                "report_card_records",
                "updated_at"
            )
        ) {

            $select .= ",
                r.updated_at
            ";

        }
        elseif (
            columnExists(
                $conn,
                "report_card_records",
                "created_at"
            )
        ) {

            $select .= ",
                r.created_at
            ";
        }


        $joins = "";


        if (
            tableExists(
                $conn,
                "students"
            )
        ) {

            $joins .= "

                LEFT JOIN students s
                    ON s.id = r.student_id
            ";
        }


        if (
            tableExists(
                $conn,
                "classes"
            )
        ) {

            $joins .= "

                LEFT JOIN classes c
                    ON c.id = r.class_id
            ";
        }


        if (
            tableExists(
                $conn,
                "terms"
            )
        ) {

            $joins .= "

                LEFT JOIN terms t
                    ON t.id = r.term_id
            ";
        }


        $studentName = "";

        if (
            tableExists(
                $conn,
                "students"
            )
        ) {

            $studentName = "

                CONCAT(
                    COALESCE(
                        s.first_name,
                        ''
                    ),
                    ' ',
                    COALESCE(
                        s.last_name,
                        ''
                    )
                ) AS student_name,

            ";
        }


        $className = "";

        if (
            tableExists(
                $conn,
                "classes"
            )
        ) {

            $className = "

                c.class_name,

            ";
        }


        $termName = "";

        if (
            tableExists(
                $conn,
                "terms"
            )
        ) {

            $termName = "

                t.term_name,

            ";
        }


        $orderBy =
            columnExists(
                $conn,
                "report_card_records",
                "updated_at"
            )
            ? "r.updated_at"
            : "r.id";


        $sql = "
            SELECT

                r.id,

                $studentName

                $className

                $termName

                r.student_id,

                r.class_id,

                r.term_id
        ";


        if (
            columnExists(
                $conn,
                "report_card_records",
                "report_status"
            )
        ) {

            $sql .= ",
                r.report_status
            ";
        }
        else {

            $sql .= ",
                '' AS report_status
            ";
        }


        if (
            columnExists(
                $conn,
                "report_card_records",
                "updated_at"
            )
        ) {

            $sql .= ",
                r.updated_at
            ";
        }
        elseif (
            columnExists(
                $conn,
                "report_card_records",
                "created_at"
            )
        ) {

            $sql .= ",
                r.created_at AS updated_at
            ";
        }
        else {

            $sql .= ",
                NULL AS updated_at
            ";
        }


        $sql .= "

            FROM report_card_records r

            $joins

            ORDER BY $orderBy DESC

            LIMIT 8
        ";


        $stmt =
            $conn->query(
                $sql
            );

        $recentReports =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (Throwable $e) {

        $recentReports = [];
    }
}


/*
|--------------------------------------------------------------------------
| SYSTEM HEALTH
|--------------------------------------------------------------------------
*/

$requiredTables = [

    "students",
    "teachers",
    "classes",
    "subjects",
    "academic_years",
    "terms",
    "report_card_records",
    "report_card_results"

];

$availableTables = 0;

foreach (
    $requiredTables
    as $table
) {

    if (
        tableExists(
            $conn,
            $table
        )
    ) {

        $availableTables++;
    }
}


$systemHealth =
    count(
        $requiredTables
    ) > 0
    ? round(
        (
            $availableTables /
            count($requiredTables)
        ) * 100
    )
    : 0;


$healthLabel =
    $systemHealth >= 100
    ? "Operational"
    : (
        $systemHealth >= 75
        ? "Mostly Ready"
        : "Attention Required"
    );


/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

$today =
    date(
        "l, d F Y"
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    HIBS Reports | Administration
</title>


<style>

/*
|--------------------------------------------------------------------------
| HIBS ADMIN DASHBOARD
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}


:root {

    --ink: #243238;

    --ink-soft: #54656c;

    --muted: #879399;

    --line: #e1e3e1;

    --paper: #ffffff;

    --background: #f4f5f3;

    --panel: #ffffff;

    --sidebar: #263238;

    --sidebar-light: #37474f;

    --accent: #607d8b;

    --success: #557a63;

    --warning: #9a7a42;

    --danger: #9a5c5c;

}


body {

    margin: 0;

    background:
        var(--background);

    color:
        var(--ink);

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

}


/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

.sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: 245px;

    height: 100vh;

    background:
        var(--sidebar);

    color: white;

    padding: 25px 16px;

    overflow-y: auto;

}


.brand {

    padding:
        4px 12px 24px;

    border-bottom:
        1px solid
        rgba(255,255,255,.12);

    margin-bottom: 23px;

}


.brand-name {

    font-size: 18px;

    font-weight: 700;

    letter-spacing: 1.4px;

}


.brand-school {

    margin-top: 7px;

    color: #b7c0c4;

    font-size: 8px;

    line-height: 1.7;

    letter-spacing: .8px;

}


.section-label {

    margin:
        0 10px 7px;

    color: #879399;

    font-size: 7px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 1px;

}


.nav {

    display: flex;

    flex-direction: column;

    gap: 3px;

}


.nav a {

    display: flex;

    align-items: center;

    gap: 11px;

    min-height: 39px;

    padding:
        0 11px;

    color: #dce2e4;

    text-decoration: none;

    border-radius: 5px;

    font-size: 9px;

}


.nav a:hover {

    background:
        var(--sidebar-light);

}


.nav a.active {

    background:
        #536a73;

    color: white;

}


.nav-icon {

    width: 20px;

    text-align: center;

    opacity: .85;

}


.sidebar-bottom {

    margin-top: 25px;

    padding-top: 17px;

    border-top:
        1px solid
        rgba(255,255,255,.1);

}


.user-mini {

    display: flex;

    align-items: center;

    gap: 9px;

    padding:
        6px 10px;

}


.avatar {

    width: 29px;

    height: 29px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #546e7a;

    border-radius: 50%;

    font-size: 10px;

    font-weight: 700;

}


.user-name {

    font-size: 8px;

    font-weight: 600;

}


.user-role {

    margin-top: 2px;

    color: #9eaaae;

    font-size: 6px;

}


/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

.main {

    margin-left: 245px;

    min-height: 100vh;

}


.topbar {

    height: 70px;

    padding:
        0 32px;

    background:
        var(--paper);

    border-bottom:
        1px solid
        var(--line);

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.topbar-left h1 {

    margin: 0;

    font-size: 17px;

    font-weight: 600;

}


.topbar-left p {

    margin:
        4px 0 0;

    color: var(--muted);

    font-size: 8px;

}


.date {

    color:
        var(--muted);

    font-size: 8px;

}


/*
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
*/

.content {

    padding:
        30px 32px 45px;

    max-width: 1500px;

}


.welcome {

    margin-bottom: 24px;

}


.welcome h2 {

    margin: 0;

    font-size: 24px;

    font-weight: 600;

}


.welcome p {

    margin:
        7px 0 0;

    color: var(--muted);

    font-size: 9px;

}


/*
|--------------------------------------------------------------------------
| STAT CARDS
|--------------------------------------------------------------------------
*/

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 13px;

    margin-bottom: 20px;

}


.stat {

    position: relative;

    min-height: 125px;

    padding: 19px;

    background:
        var(--paper);

    border:
        1px solid
        var(--line);

}


.stat::before {

    content: "";

    position: absolute;

    top: 0;
    left: 0;

    width: 3px;

    height: 100%;

    background:
        var(--accent);

}


.stat-label {

    color:
        var(--muted);

    font-size: 7px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .8px;

}


.stat-number {

    margin-top: 11px;

    font-size: 27px;

    font-weight: 600;

}


.stat-description {

    margin-top: 6px;

    color:
        #899397;

    font-size: 7px;

}


/*
|--------------------------------------------------------------------------
| STATUS CARDS
|--------------------------------------------------------------------------
*/

.status-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 13px;

    margin-bottom: 20px;

}


.status {

    padding:
        16px 17px;

    background:
        var(--paper);

    border:
        1px solid
        var(--line);

}


.status-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.status-label {

    color:
        var(--muted);

    font-size: 7px;

    font-weight: 700;

    text-transform: uppercase;

}


.status-number {

    font-size: 20px;

    font-weight: 600;

}


.status-line {

    margin-top: 10px;

    height: 3px;

    background:
        #e9ebea;

}


.status-fill {

    height: 100%;

    width: 70%;

    background:
        var(--accent);

}


/*
|--------------------------------------------------------------------------
| TWO COLUMN AREA
|--------------------------------------------------------------------------
*/

.columns {

    display: grid;

    grid-template-columns:
        minmax(0, 1.7fr)
        minmax(290px, .8fr);

    gap: 18px;

}


.panel {

    background:
        var(--paper);

    border:
        1px solid
        var(--line);

    margin-bottom: 18px;

}


.panel-header {

    min-height: 60px;

    padding:
        0 18px;

    border-bottom:
        1px solid
        var(--line);

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.panel-title {

    font-size: 11px;

    font-weight: 600;

}


.panel-subtitle {

    margin-top: 3px;

    color:
        var(--muted);

    font-size: 7px;

}


.panel-link {

    color:
        var(--accent);

    text-decoration: none;

    font-size: 7px;

}


.panel-link:hover {

    text-decoration: underline;

}


/*
|--------------------------------------------------------------------------
| REPORT ACTIVITY
|--------------------------------------------------------------------------
*/

.activity {

    padding: 0;

}


.activity-row {

    display: grid;

    grid-template-columns:
        1.7fr
        1fr
        1fr
        auto;

    gap: 12px;

    align-items: center;

    padding:
        13px 18px;

    border-bottom:
        1px solid
        #eef0ee;

}


.activity-row:last-child {

    border-bottom: 0;

}


.student-name {

    font-size: 8px;

    font-weight: 600;

}


.student-meta {

    margin-top: 3px;

    color:
        var(--muted);

    font-size: 6px;

}


.cell {

    color:
        #657379;

    font-size: 7px;

}


.badge {

    display: inline-block;

    padding:
        5px 7px;

    border-radius: 3px;

    font-size: 6px;

    font-weight: 700;

    text-transform: uppercase;

}


.badge-published {

    background: #e9f1eb;

    color: var(--success);

}


.badge-pending {

    background: #f5f0e5;

    color: var(--warning);

}


.badge-draft {

    background: #eef0f1;

    color: #69777d;

}


.badge-returned {

    background: #f5eaea;

    color: var(--danger);

}


/*
|--------------------------------------------------------------------------
| QUICK ACTIONS
|--------------------------------------------------------------------------
*/

.quick-grid {

    display: grid;

    grid-template-columns:
        1fr
        1fr;

    gap: 8px;

    padding: 16px;

}


.quick {

    min-height: 74px;

    padding: 12px;

    border:
        1px solid
        var(--line);

    color:
        var(--ink);

    text-decoration: none;

    background:
        #fafbfa;

}


.quick:hover {

    border-color:
        #b9c1c4;

    background:
        #f3f5f4;

}


.quick-icon {

    font-size: 16px;

}


.quick-title {

    margin-top: 8px;

    font-size: 8px;

    font-weight: 600;

}


.quick-text {

    margin-top: 3px;

    color:
        var(--muted);

    font-size: 6px;

}


/*
|--------------------------------------------------------------------------
| ACADEMIC SNAPSHOT
|--------------------------------------------------------------------------
*/

.snapshot {

    padding: 17px;

}


.snapshot-item {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding:
        11px 0;

    border-bottom:
        1px solid
        #edf0ee;

}


.snapshot-item:last-child {

    border-bottom: 0;

}


.snapshot-label {

    color:
        var(--muted);

    font-size: 7px;

}


.snapshot-value {

    font-size: 9px;

    font-weight: 600;

}


/*
|--------------------------------------------------------------------------
| SYSTEM HEALTH
|--------------------------------------------------------------------------
*/

.health {

    padding: 18px;

}


.health-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.health-title {

    font-size: 9px;

    font-weight: 600;

}


.health-status {

    color:
        var(--success);

    font-size: 7px;

    font-weight: 700;

}


.health-bar {

    margin-top: 12px;

    height: 5px;

    background:
        #e8ebe9;

}


.health-progress {

    height: 100%;

    background:
        var(--success);

}


.health-text {

    margin-top: 8px;

    color:
        var(--muted);

    font-size: 7px;

}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    padding:
        35px 18px;

    color:
        var(--muted);

    text-align: center;

    font-size: 8px;

}


/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.footer {

    margin-top: 8px;

    padding-top: 18px;

    border-top:
        1px solid
        var(--line);

    color:
        var(--muted);

    font-size: 7px;

    text-align: center;

}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media(max-width:1150px) {

    .stats,
    .status-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .columns {

        grid-template-columns:
            1fr;

    }

}


@media(max-width:800px) {

    .sidebar {

        position: static;

        width: 100%;

        height: auto;

    }


    .main {

        margin-left: 0;

    }


    .nav {

        display: grid;

        grid-template-columns:
            repeat(2, 1fr);

    }


    .sidebar-bottom {

        display: none;

    }


    .content {

        padding:
            22px 16px;

    }


    .topbar {

        padding:
            0 16px;

    }

}


@media(max-width:600px) {

    .stats,
    .status-grid {

        grid-template-columns:
            1fr;

    }


    .activity-row {

        grid-template-columns:
            1fr;

        gap: 6px;

    }


    .quick-grid {

        grid-template-columns:
            1fr;

    }


    .welcome h2 {

        font-size: 20px;

    }



/* =========================================================
   HIBS DASHBOARD - READABILITY IMPROVEMENT
========================================================= */

body {
    font-size: 14px;
}

.brand-name {
    font-size: 21px;
}

.brand-school {
    font-size: 10px;
}

.section-label {
    font-size: 12px;
}

.nav a {
    min-height: 45px;
    font-size: 13px;
}

.nav-icon {
    width: 24px;
    font-size: 15px;
}

.user-name {
    font-size: 11px;
}

.user-role {
    font-size: 11px;
}

.topbar {
    height: 78px;
}

.topbar-left h1 {
    font-size: 22px;
}

.topbar-left p {
    font-size: 13px;
}

.date {
    font-size: 13px;
}

.welcome h2 {
    font-size: 28px;
}

.welcome p {
    font-size: 13px;
}

.stat {
    min-height: 145px;
    padding: 22px;
}

.stat-label {
    font-size: 13px;
}

.stat-number {
    margin-top: 13px;
    font-size: 32px;
}

.stat-description {
    margin-top: 8px;
    font-size: 13px;
}

.status {
    padding: 19px 20px;
}

.status-label {
    font-size: 12px;
}

.status-number {
    font-size: 23px;
}

.panel-header {
    min-height: 70px;
    padding: 0 22px;
}

.panel-title {
    font-size: 15px;
}

.panel-subtitle {
    margin-top: 5px;
    font-size: 11px;
}

.panel-link {
    font-size: 12px;
}

.activity-row {
    padding: 16px 22px;
}

.student-name {
    font-size: 13px;
}

.student-meta {
    margin-top: 4px;
    font-size: 11px;
}

.cell {
    font-size: 12px;
}

.badge {
    padding: 6px 9px;
    font-size: 11px;
}

.quick-grid {
    padding: 20px;
    gap: 11px;
}

.quick {
    min-height: 95px;
    padding: 15px;
}

.quick-icon {
    font-size: 20px;
}

.quick-title {
    margin-top: 9px;
    font-size: 14px;
}

.quick-text {
    margin-top: 5px;
    font-size: 11px;
}

.snapshot {
    padding: 20px;
}

.snapshot-item {
    padding: 14px 0;
}

.snapshot-label {
    font-size: 12px;
}

.snapshot-value {
    font-size: 13px;
}

.health {
    padding: 21px;
}

.health-title {
    font-size: 14px;
}

.health-status {
    font-size: 12px;
}

.health-text {
    margin-top: 9px;
    font-size: 11px;
}

.footer {
    font-size: 12px;
}

.empty {
    padding: 45px 20px;
    font-size: 13px;
}








    
}

</style>

</head>


<body>


<!-- ======================================================
     SIDEBAR
======================================================= -->

<aside class="sidebar">


<div class="brand">

    <div class="brand-name">
        HIBS REPORTS
    </div>

    <div class="brand-school">

        HILLTOP INTERNATIONAL<br>
        BRITISH SCHOOL

    </div>

</div>


<div class="section-label">
    Administration
</div>


<nav class="nav">


<a
    href="dashboard.php"
    class="active"
>

    <span class="nav-icon">
        ▦
    </span>

    Dashboard

</a>


<a
    href="academic_setup.php"
>

    <span class="nav-icon">
        ◫
    </span>

    Academic Setup

</a>


<a
    href="teacher_assignments.php"
>

    <span class="nav-icon">
        ⟷
    </span>

    Teacher Assignments

</a>


<a
    href="students.php"
>

    <span class="nav-icon">
        ♙
    </span>

    Students

</a>


<a
    href="teachers.php"
>

    <span class="nav-icon">
        ♙
    </span>

    Teachers

</a>


<a
    href="classes.php"
>

    <span class="nav-icon">
        □
    </span>

    Classes

</a>


<a
    href="subjects.php"
>

    <span class="nav-icon">
        ◇
    </span>

    Subjects

</a>


<a
    href="mark_submissions.php"
>

    <span class="nav-icon">
        ✓
    </span>

    Mark Submissions

</a>


<a
    href="reports.php"
>

    <span class="nav-icon">
        ▤
    </span>

    Reports

</a>


<a
    href="report_approval.php"
>

    <span class="nav-icon">
        ✓
    </span>

    Report Approval

</a>


<a
    href="publish_report.php"
>

    <span class="nav-icon">
        ↑
    </span>

    Publish Reports

</a>


<a
    href="analytics.php"
>

    <span class="nav-icon">
        ◒
    </span>

    Analytics

</a>


<a
    href="database_check.php"
>

    <span class="nav-icon">
        ◉
    </span>

    Database Check

</a>


<a
    href="accounts.php"
>

    <span class="nav-icon">
        ◉
    </span>

   Account Management

</a>
    

<a
    href="settings.php"
>

    <span class="nav-icon">
        ⚙
    </span>

    Settings

</a>


</nav>


<div class="sidebar-bottom">


<div class="user-mini">


<div class="avatar">

    <?= h(
        strtoupper(
            substr(
                $adminName,
                0,
                1
            )
        )
    ) ?>

</div>


<div>

    <div class="user-name">

        <?= h(
            $adminName
        ) ?>

    </div>

    <div class="user-role">

        System Administrator

    </div>

</div>


</div>


</div>


</aside>


<!-- ======================================================
     MAIN
======================================================= -->

<div class="main">


<header class="topbar">


<div class="topbar-left">

    <h1>
        Administration Dashboard
    </h1>

    <p>
        HIBS Academic Reporting System
    </p>

</div>


<div class="date">

    <?= h(
        $today
    ) ?>

</div>


</header>


<main class="content">


<!-- ====================================================
     WELCOME
===================================================== -->

<section class="welcome">

    <h2>

        Good day,
        <?= h(
            $adminName
        ) ?>.

    </h2>

    <p>

        Here is the current overview of
        Hilltop International British School's
        academic reporting system.

    </p>

</section>


<!-- ====================================================
     MAIN STATISTICS
===================================================== -->

<section class="stats">


<div class="stat">

    <div class="stat-label">
        Students
    </div>

    <div class="stat-number">

        <?= number_format(
            $totalStudents
        ) ?>

    </div>

    <div class="stat-description">

        Registered students

    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Teachers
    </div>

    <div class="stat-number">

        <?= number_format(
            $totalTeachers
        ) ?>

    </div>

    <div class="stat-description">

        Registered teaching staff

    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Classes
    </div>

    <div class="stat-number">

        <?= number_format(
            $totalClasses
        ) ?>

    </div>

    <div class="stat-description">

        Active academic classes

    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Subjects
    </div>

    <div class="stat-number">

        <?= number_format(
            $totalSubjects
        ) ?>

    </div>

    <div class="stat-description">

        Academic subjects

    </div>

</div>


</section>


<!-- ====================================================
     REPORT STATUS
===================================================== -->

<section class="status-grid">


<div class="status">

    <div class="status-top">

        <div class="status-label">
            Reports
        </div>

        <div class="status-number">

            <?= number_format(
                $totalReports
            ) ?>

        </div>

    </div>

    <div class="status-line">

        <div
            class="status-fill"
            style="
                width:
                <?= $totalReports > 0 ? '100' : '0' ?>%;
            "
        ></div>

    </div>

</div>


<div class="status">

    <div class="status-top">

        <div class="status-label">
            Awaiting Approval
        </div>

        <div class="status-number">

            <?= number_format(
                $pendingReports
            ) ?>

        </div>

    </div>

    <div class="status-line">

        <div
            class="status-fill"
            style="
                width:
                <?= $totalReports > 0
                    ? min(
                        100,
                        ($pendingReports / $totalReports) * 100
                    )
                    : 0
                ?>%;
            "
        ></div>

    </div>

</div>


<div class="status">

    <div class="status-top">

        <div class="status-label">
            Published
        </div>

        <div class="status-number">

            <?= number_format(
                $publishedReports
            ) ?>

        </div>

    </div>

    <div class="status-line">

        <div
            class="status-fill"
            style="
                width:
                <?= $totalReports > 0
                    ? min(
                        100,
                        ($publishedReports / $totalReports) * 100
                    )
                    : 0
                ?>%;
            "
        ></div>

    </div>

</div>


<div class="status">

    <div class="status-top">

        <div class="status-label">
            Mark Submissions
        </div>

        <div class="status-number">

            <?= number_format(
                $pendingMarkSubmissions
            ) ?>

        </div>

    </div>

    <div class="status-line">

        <div
            class="status-fill"
            style="
                width:
                <?= $markSubmissions > 0
                    ? min(
                        100,
                        ($pendingMarkSubmissions / $markSubmissions) * 100
                    )
                    : 0
                ?>%;
            "
        ></div>

    </div>

</div>


</section>


<!-- ====================================================
     COLUMNS
===================================================== -->

<div class="columns">


<!-- ====================================================
     LEFT
===================================================== -->

<div>


<section class="panel">


<div class="panel-header">

    <div>

        <div class="panel-title">
            Recent Report Activity
        </div>

        <div class="panel-subtitle">
            Latest report records in the system
        </div>

    </div>


    <a
        href="reports.php"
        class="panel-link"
    >
        View all
    </a>

</div>


<div class="activity">


<?php if (
    count($recentReports)
): ?>


<?php foreach (
    $recentReports
    as $report
): ?>


<div class="activity-row">


<div>


<div class="student-name">

<?php

$name =
    trim(
        (string)
        (
            $report[
                "student_name"
            ]
            ?? ""
        )
    );

echo h(
    $name !== ""
    ? $name
    : "Student #"
      .
      (
          $report[
              "student_id"
          ]
          ?? ""
      )
);

?>

</div>


<div class="student-meta">

    <?= h(
        $report[
            "class_name"
        ]
        ?? "Class not specified"
    ) ?>

</div>


</div>


<div class="cell">

    <?= h(
        $report[
            "term_name"
        ]
        ?? "Term"
    ) ?>

</div>


<div class="cell">

<?php

$status =
    strtolower(
        trim(
            (string)
            (
                $report[
                    "report_status"
                ]
                ?? ""
            )
        )
    );

if (
    $status === ""
) {

    $status =
        "draft";
}

?>

<?php if (
    $status === "published"
): ?>

<span class="badge badge-published">
    Published
</span>

<?php elseif (
    in_array(
        $status,
        [
            "pending",
            "submitted",
            "awaiting approval",
            "awaiting_approval",
            "approved"
        ],
        true
    )
): ?>

<span class="badge badge-pending">
    <?= h(
        $status
    ) ?>
</span>

<?php elseif (
    in_array(
        $status,
        [
            "returned",
            "rejected"
        ],
        true
    )
): ?>

<span class="badge badge-returned">
    <?= h(
        $status
    ) ?>
</span>

<?php else: ?>

<span class="badge badge-draft">
    <?= h(
        $status
    ) ?>
</span>

<?php endif; ?>

</div>


<div class="cell">

<?php

if (
    !empty(
        $report[
            "updated_at"
        ]
    )
) {

    echo h(
        date(
            "d M Y",
            strtotime(
                (string)
                $report[
                    "updated_at"
                ]
            )
        )
    );

}
else {

    echo "—";
}

?>

</div>


</div>


<?php endforeach; ?>


<?php else: ?>


<div class="empty">

    No report activity is available yet.

</div>


<?php endif; ?>


</div>


</section>


<!-- ====================================================
     SYSTEM INFORMATION
===================================================== -->

<section class="panel">


<div class="panel-header">

    <div>

        <div class="panel-title">
            System Overview
        </div>

        <div class="panel-subtitle">
            Current HIBS academic database structure
        </div>

    </div>

</div>


<div class="snapshot">


<div class="snapshot-item">

    <span class="snapshot-label">
        Academic Years
    </span>

    <span class="snapshot-value">

        <?= number_format(
            $totalAcademicYears
        ) ?>

    </span>

</div>


<div class="snapshot-item">

    <span class="snapshot-label">
        Terms
    </span>

    <span class="snapshot-value">

        <?= number_format(
            $totalTerms
        ) ?>

    </span>

</div>


<div class="snapshot-item">

    <span class="snapshot-label">
        Teacher Assignments
    </span>

    <span class="snapshot-value">

        <?= number_format(
            $totalAssignments
        ) ?>

    </span>

</div>


<div class="snapshot-item">

    <span class="snapshot-label">
        Subject Results
    </span>

    <span class="snapshot-value">

        <?= number_format(
            $totalResults
        ) ?>

    </span>

</div>


</div>


</section>


</div>


<!-- ====================================================
     RIGHT
===================================================== -->

<div>


<section class="panel">


<div class="panel-header">

    <div>

        <div class="panel-title">
            Quick Actions
        </div>

        <div class="panel-subtitle">
            Common administration tasks
        </div>

    </div>

</div>


<div class="quick-grid">


<a
    href="student_add.php"
    class="quick"
>

    <div class="quick-icon">
        +
    </div>

    <div class="quick-title">
        Add Student
    </div>

    <div class="quick-text">
        Register a new student
    </div>

</a>


<a
    href="teachers.php"
    class="quick"
>

    <div class="quick-icon">
        +
    </div>

    <div class="quick-title">
        Manage Teachers
    </div>

    <div class="quick-text">
        Teaching staff
    </div>

</a>


<a
    href="academic_setup.php"
    class="quick"
>

    <div class="quick-icon">
        ◫
    </div>

    <div class="quick-title">
        Academic Setup
    </div>

    <div class="quick-text">
        Years, terms and classes
    </div>

</a>


<a
    href="teacher_assignments.php"
    class="quick"
>

    <div class="quick-icon">
        ⟷
    </div>

    <div class="quick-title">
        Assign Teachers
    </div>

    <div class="quick-text">
        Class and subject
    </div>

</a>


<a
    href="mark_submissions.php"
    class="quick"
>

    <div class="quick-icon">
        ✓
    </div>

    <div class="quick-title">
        Review Marks
    </div>

    <div class="quick-text">
        Teacher submissions
    </div>

</a>


<a
    href="report_approval.php"
    class="quick"
>

    <div class="quick-icon">
        ✓
    </div>

    <div class="quick-title">
        Approve Reports
    </div>

    <div class="quick-text">
        Review completed reports
    </div>

</a>


<a
    href="publish_report.php"
    class="quick"
>

    <div class="quick-icon">
        ↑
    </div>

    <div class="quick-title">
        Publish Reports
    </div>

    <div class="quick-text">
        Release reports to students
    </div>

</a>


<a
    href="analytics.php"
    class="quick"
>

    <div class="quick-icon">
        ◒
    </div>

    <div class="quick-title">
        Analytics
    </div>

    <div class="quick-text">
        Academic performance
    </div>

</a>


</div>


</section>


<!-- ====================================================
     ACADEMIC PERIOD
===================================================== -->

<section class="panel">


<div class="panel-header">

    <div>

        <div class="panel-title">
            Academic Period
        </div>

        <div class="panel-subtitle">
            Current system configuration
        </div>

    </div>

</div>


<div class="snapshot">


<div class="snapshot-item">

    <span class="snapshot-label">
        Academic Year
    </span>

    <span class="snapshot-value">

        <?= h(
            $currentAcademicYear
        ) ?>

    </span>

</div>


<div class="snapshot-item">

    <span class="snapshot-label">
        Current Term
    </span>

    <span class="snapshot-value">

        <?= h(
            $currentTerm
        ) ?>

    </span>

</div>


<div class="snapshot-item">

    <span class="snapshot-label">
        Draft Reports
    </span>

    <span class="snapshot-value">

        <?= number_format(
            $draftReports
        ) ?>

    </span>

</div>


<div class="snapshot-item">

    <span class="snapshot-label">
        Returned Reports
    </span>

    <span class="snapshot-value">

        <?= number_format(
            $returnedReports
        ) ?>

    </span>

</div>


</div>


</section>


<!-- ====================================================
     SYSTEM HEALTH
===================================================== -->

<section class="panel">


<div class="health">


<div class="health-header">


<div class="health-title">

    Database Structure

</div>


<div class="health-status">

    <?= h(
        $healthLabel
    ) ?>

</div>


</div>


<div class="health-bar">


<div
    class="health-progress"
    style="
        width:
        <?= $systemHealth ?>%;
    "
></div>


</div>


<div class="health-text">

    <?= $availableTables ?>
    of
    <?= count(
        $requiredTables
    ) ?>
    core reporting tables detected.

</div>


</div>


</section>


</div>


</div>


<!-- ====================================================
     FOOTER
===================================================== -->

<div class="footer">

    HIBS Reports · Hilltop International British School
    · Academic Administration System

</div>


</main>


</div>


</body>

</html>
