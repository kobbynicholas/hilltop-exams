<?php

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTING SYSTEM
| ADMIN REPORT DETAILS
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
| REPORT ID
|--------------------------------------------------------------------------
*/

$reportId =
    filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );


if (!$reportId) {

    header(
        "Location: reports.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$report = null;

$subjects = [];

$results = [];

$error = "";

$success = "";


/*
|--------------------------------------------------------------------------
| LOAD REPORT
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | REPORT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT

            r.*,

            s.student_id AS student_number,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.gender,
            s.dob,
            s.photo,

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

        WHERE r.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $reportId
    ]);

    $report =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$report) {

        throw new Exception(
            "The requested report could not be found."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUBJECTS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT

            id,
            subject_name

        FROM subjects

        ORDER BY subject_name ASC
    ");

    $stmt->execute();

    $subjects =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | RESULTS
    |--------------------------------------------------------------------------
    |
    | The system checks the common report-result table.
    |
    */

    $resultTableExists = false;


    try {

        $check =
            $conn->query("
                SHOW TABLES LIKE 'report_card_results'
            ");

        $resultTableExists =
            $check->rowCount() > 0;

    } catch (
        Throwable $e
    ) {

        $resultTableExists = false;
    }


    if (
        $resultTableExists
    ) {

        $stmt = $conn->prepare("
            SELECT

                rr.*,

                s.subject_name

            FROM report_card_results rr

            INNER JOIN subjects s
                ON s.id = rr.subject_id

            WHERE

                rr.report_id = ?

            ORDER BY

                s.subject_name ASC
        ");

        $stmt->execute([
            $reportId
        ]);

        $results =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }


} catch (
    Throwable $e
) {

    $error =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| SAVE REPORT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $report
) {

    $action =
        $_POST["action"]
        ?? "save";


    /*
    |--------------------------------------------------------------------------
    | BASIC VALUES
    |--------------------------------------------------------------------------
    */

    $daysOpened =
        max(
            0,
            (int)(
                $_POST["days_opened"]
                ?? 0
            )
        );


    $daysPresent =
        max(
            0,
            (int)(
                $_POST["days_present"]
                ?? 0
            )
        );


    $daysAbsent =
        max(
            0,
            $daysOpened - $daysPresent
        );


    $position =
        trim(
            $_POST["position"]
            ?? ""
        );


    $classSize =
        trim(
            $_POST["class_size"]
            ?? ""
        );


    $promotionStatus =
        trim(
            $_POST["promotion_status"]
            ?? ""
        );


    $conduct =
        trim(
            $_POST["conduct"]
            ?? ""
        );


    $teacherComment =
        trim(
            $_POST["teacher_comment"]
            ?? ""
        );


    $headteacherComment =
        trim(
            $_POST["headteacher_comment"]
            ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | AVERAGE
    |--------------------------------------------------------------------------
    */

    $averageScore =
        trim(
            $_POST["average_score"]
            ?? ""
        );


    if (
        $averageScore !== ""
    ) {

        $averageScore =
            (float)$averageScore;

    } else {

        $averageScore = null;
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    $currentStatus =
        $report[
            "report_status"
        ]
        ?? "Draft";


    $newStatus =
        $currentStatus;


    if (
        $action === "save"
    ) {

        /*
        | Do not move an approved or published report
        | backwards accidentally.
        */

        if (
            $currentStatus === "Draft"
        ) {

            $newStatus =
                "Draft";
        }

    } elseif (
        $action === "submit"
    ) {

        /*
        | Submit the completed report for approval.
        */

        if (
            $currentStatus === "Draft"
        ) {

            $newStatus =
                "Draft";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE REPORT
    |--------------------------------------------------------------------------
    */

    try {

        $conn->beginTransaction();


        $stmt = $conn->prepare("
            UPDATE report_card_records

            SET

                average_score = ?,

                position = ?,

                class_size = ?,

                days_opened = ?,

                days_present = ?,

                days_absent = ?,

                conduct = ?,

                promotion_status = ?,

                teacher_comment = ?,

                headteacher_comment = ?,

                report_status = ?,

                updated_at = NOW()

            WHERE id = ?
        ");


        $stmt->execute([

            $averageScore,

            $position !== ""
                ? $position
                : null,

            $classSize !== ""
                ? $classSize
                : null,

            $daysOpened,

            $daysPresent,

            $daysAbsent,

            $conduct !== ""
                ? $conduct
                : null,

            $promotionStatus !== ""
                ? $promotionStatus
                : null,

            $teacherComment !== ""
                ? $teacherComment
                : null,

            $headteacherComment !== ""
                ? $headteacherComment
                : null,

            $newStatus,

            $reportId

        ]);


        /*
        |--------------------------------------------------------------------------
        | SUBJECT RESULTS
        |--------------------------------------------------------------------------
        */

        if (
            $resultTableExists
        ) {

            $subjectScores =
                $_POST["subject_score"]
                ?? [];


            $subjectGrades =
                $_POST["subject_grade"]
                ?? [];


            $subjectComments =
                $_POST["subject_comment"]
                ?? [];


            foreach (
                $subjects
                as $subject
            ) {

                $subjectId =
                    (int)$subject["id"];


                $score =
                    trim(
                        $subjectScores[
                            $subjectId
                        ]
                        ?? ""
                    );


                $grade =
                    trim(
                        $subjectGrades[
                            $subjectId
                        ]
                        ?? ""
                    );


                $comment =
                    trim(
                        $subjectComments[
                            $subjectId
                        ]
                        ?? ""
                    );


                /*
                |--------------------------------------------------------------------------
                | CHECK EXISTING RESULT
                |--------------------------------------------------------------------------
                */

                $check =
                    $conn->prepare("
                        SELECT id

                        FROM report_card_results

                        WHERE

                            report_id = ?

                            AND subject_id = ?

                        LIMIT 1
                    ");

                $check->execute([

                    $reportId,

                    $subjectId

                ]);


                $existing =
                    $check->fetch(
                        PDO::FETCH_ASSOC
                    );


                /*
                |--------------------------------------------------------------------------
                | NOTHING ENTERED
                |--------------------------------------------------------------------------
                */

                if (
                    $score === "" &&
                    $grade === "" &&
                    $comment === ""
                ) {

                    if (
                        $existing
                    ) {

                        $delete =
                            $conn->prepare("
                                DELETE FROM
                                    report_card_results

                                WHERE id = ?
                            ");

                        $delete->execute([

                            $existing["id"]

                        ]);
                    }

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | INSERT
                |--------------------------------------------------------------------------
                */

                if (
                    !$existing
                ) {

                    $insert =
                        $conn->prepare("
                            INSERT INTO
                                report_card_results
                            (
                                report_id,
                                subject_id,
                                score,
                                grade,
                                comment,
                                created_at,
                                updated_at
                            )

                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                NOW(),
                                NOW()
                            )
                        ");


                    $insert->execute([

                        $reportId,

                        $subjectId,

                        $score !== ""
                            ? (float)$score
                            : null,

                        $grade !== ""
                            ? $grade
                            : null,

                        $comment !== ""
                            ? $comment
                            : null

                    ]);


                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE
                    |--------------------------------------------------------------------------
                    */

                    $update =
                        $conn->prepare("
                            UPDATE
                                report_card_results

                            SET

                                score = ?,

                                grade = ?,

                                comment = ?,

                                updated_at = NOW()

                            WHERE id = ?
                        ");


                    $update->execute([

                        $score !== ""
                            ? (float)$score
                            : null,

                        $grade !== ""
                            ? $grade
                            : null,

                        $comment !== ""
                            ? $comment
                            : null,

                        $existing["id"]

                    ]);
                }
            }
        }


        $conn->commit();


        /*
        |--------------------------------------------------------------------------
        | SUCCESS MESSAGE
        |--------------------------------------------------------------------------
        */

        if (
            $action === "submit"
        ) {

            $success =
                "Report saved successfully and is ready for administrative approval.";

        } else {

            $success =
                "Report saved successfully.";
        }


        /*
        |--------------------------------------------------------------------------
        | RELOAD REPORT
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT

                r.*,

                s.student_id AS student_number,
                s.first_name,
                s.middle_name,
                s.last_name,
                s.gender,
                s.dob,
                s.photo,

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

            WHERE r.id = ?

            LIMIT 1
        ");

        $stmt->execute([
            $reportId
        ]);

        $report =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | RELOAD RESULTS
        |--------------------------------------------------------------------------
        */

        if (
            $resultTableExists
        ) {

            $stmt = $conn->prepare("
                SELECT

                    rr.*,

                    s.subject_name

                FROM report_card_results rr

                INNER JOIN subjects s
                    ON s.id = rr.subject_id

                WHERE rr.report_id = ?

                ORDER BY s.subject_name ASC
            ");

            $stmt->execute([
                $reportId
            ]);

            $results =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }


    } catch (
        Throwable $e
    ) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| RESULT LOOKUP
|--------------------------------------------------------------------------
*/

$resultBySubject = [];

foreach (
    $results
    as $result
) {

    $resultBySubject[
        (int)$result["subject_id"]
    ] =
        $result;
}


/*
|--------------------------------------------------------------------------
| STUDENT NAME
|--------------------------------------------------------------------------
*/

$studentName = "";

if ($report) {

    $studentName =
        trim(
            implode(
                " ",
                array_filter([
                    $report["first_name"] ?? "",
                    $report["middle_name"] ?? "",
                    $report["last_name"] ?? ""
                ])
            )
        );
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status =
    $report[
        "report_status"
    ]
    ?? "Draft";


$statusClass =
    "draft";


if (
    $status === "Approved"
) {

    $statusClass =
        "approved";

} elseif (
    $status === "Published"
) {

    $statusClass =
        "published";
}


/*
|--------------------------------------------------------------------------
| PHOTO
|--------------------------------------------------------------------------
*/

$photoPath = "";

if (
    $report &&
    !empty(
        $report["photo"]
    )
) {

    $possiblePhotos = [

        "../uploads/students/"
        . $report["photo"],

        "../uploads/"
        . $report["photo"],

        "../assets/uploads/students/"
        . $report["photo"]

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
    HIBS Reports | Report Details
</title>


<style>

/* =========================================================
   HIBS REPORT EDITOR
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

    margin: 0;

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

    font-size: 17px;

    font-weight: 600;

    color: #263238;

}


.topbar-right {

    display: flex;

    align-items: center;

    gap: 12px;

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

    max-width: 1500px;

}


/* =========================================================
   HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;

    margin-bottom: 20px;

}


.page-header h1 {

    margin: 0;

    color: #263238;

    font-size: 24px;

    font-weight: 600;

}


.page-header p {

    margin:
        7px 0 0;

    color: #7a858a;

    font-size: 10px;

}


.back {

    display: inline-block;

    padding:
        10px 13px;

    background: #ffffff;

    border:
        1px solid
        #d4d3ce;

    color: #455a64;

    text-decoration: none;

    font-size: 9px;

    border-radius: 3px;

}


/* =========================================================
   ALERTS
========================================================= */

.alert {

    margin-bottom: 18px;

    padding: 13px 15px;

    font-size: 10px;

}


.alert-success {

    background: #eaf2ed;

    border:
        1px solid
        #c9ddcf;

    color: #42664e;

}


.alert-error {

    background: #fbf1f1;

    border:
        1px solid
        #e1c8c8;

    color: #8b4b4b;

}


/* =========================================================
   STATUS
========================================================= */

.status-box {

    margin-bottom: 20px;

    padding: 14px 17px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.status-label {

    color: #7a858a;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

}


.status {

    display: inline-block;

    padding:
        7px 10px;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

}


.status.draft {

    background: #f3eee5;

    color: #806744;

}


.status.approved {

    background: #e9eef2;

    color: #506675;

}


.status.published {

    background: #e8f1eb;

    color: #3e6b4e;

}


/* =========================================================
   STUDENT HEADER
========================================================= */

.student-card {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    padding: 22px;

    display: flex;

    align-items: center;

    gap: 18px;

    margin-bottom: 20px;

}


.student-photo {

    width: 75px;
    height: 88px;

    object-fit: cover;

    border:
        1px solid
        #c9c7c2;

}


.photo-placeholder {

    width: 75px;
    height: 88px;

    background: #edf0ef;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #8a9599;

    font-size: 7px;

    text-align: center;

}


.student-name {

    margin: 0;

    color: #263238;

    font-size: 18px;

    font-weight: 600;

}


.student-meta {

    margin-top: 7px;

    color: #7a858a;

    font-size: 9px;

}


.student-meta span {

    margin-right: 15px;

}


/* =========================================================
   PANELS
========================================================= */

.panel {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    margin-bottom: 20px;

}


.panel-header {

    padding:
        18px 21px;

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


/* =========================================================
   FORM
========================================================= */

.form-body {

    padding: 21px;

}


.form-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

}


.field label {

    display: block;

    margin-bottom: 6px;

    color: #6f7d82;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: .5px;

}


.field input,
.field select,
.field textarea {

    width: 100%;

    border:
        1px solid
        #d4d3ce;

    background: #ffffff;

    color: #455a64;

    font-family: inherit;

    font-size: 10px;

    outline: none;

}


.field input,
.field select {

    height: 38px;

    padding:
        0 10px;

}


.field textarea {

    min-height: 90px;

    padding: 10px;

    resize: vertical;

}


.field input:focus,
.field select:focus,
.field textarea:focus {

    border-color:
        #78909c;

}


/* =========================================================
   RESULTS TABLE
========================================================= */

.table-wrap {

    overflow-x: auto;

}


.results-table {

    width: 100%;

    min-width: 900px;

    border-collapse: collapse;

}


.results-table th {

    padding:
        11px 10px;

    background: #f2f3f1;

    color: #69777c;

    border-bottom:
        1px solid
        #dddcd7;

    text-align: left;

    font-size: 7px;

    text-transform: uppercase;

    letter-spacing: .5px;

}


.results-table td {

    padding:
        9px 10px;

    border-bottom:
        1px solid
        #eceae6;

    vertical-align: middle;

}


.results-table input {

    width: 100%;

    height: 34px;

    padding:
        0 8px;

    border:
        1px solid
        #d4d3ce;

    color: #455a64;

    font-size: 9px;

    outline: none;

}


.results-table input:focus {

    border-color:
        #78909c;

}


.subject-name {

    color: #455a64;

    font-size: 9px;

    font-weight: 600;

}


/* =========================================================
   COMMENTS
========================================================= */

.comments-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 18px;

}


/* =========================================================
   ACTIONS
========================================================= */

.form-actions {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    padding:
        18px 21px;

    background: #f7f7f4;

    border-top:
        1px solid
        #e5e4df;

}


.action-left,
.action-right {

    display: flex;

    gap: 8px;

}


.btn {

    border: 0;

    padding:
        11px 15px;

    font-family: inherit;

    font-size: 9px;

    font-weight: bold;

    text-decoration: none;

    cursor: pointer;

    border-radius: 3px;

}


.btn-save {

    background: #455a64;

    color: #ffffff;

}


.btn-save:hover {

    background: #263238;

}


.btn-submit {

    background: #657d70;

    color: #ffffff;

}


.btn-submit:hover {

    background: #50665a;

}


.btn-cancel {

    background: #ffffff;

    border:
        1px solid
        #d4d3ce;

    color: #68757a;

}


/* =========================================================
   LOCKED
========================================================= */

.locked {

    padding: 13px 15px;

    background: #f1f2f1;

    border:
        1px solid
        #dedfdd;

    color: #69777c;

    font-size: 9px;

    line-height: 1.6;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .form-grid {

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

    .back {

        margin-top: 12px;

    }

    .form-grid {

        grid-template-columns: 1fr;

    }

    .comments-grid {

        grid-template-columns: 1fr;

    }

    .form-actions {

        flex-direction: column;

        align-items: stretch;

    }

    .action-left,
    .action-right {

        width: 100%;

    }

    .btn {

        flex: 1;

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
            Report Details
        </div>


        <div class="topbar-right">

            <div class="admin-name">

                <?= h(
                    $_SESSION["name"]
                    ??
                    $_SESSION["username"]
                    ??
                    "Administrator"
                ) ?>

            </div>

        </div>


    </header>


    <main class="content">


        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <section class="page-header">


            <div>

                <h1>
                    Academic Report
                </h1>

                <p>
                    Complete and maintain the student's
                    official academic report.
                </p>

            </div>


            <a
                href="reports.php"
                class="back"
            >
                ← Back to Reports
            </a>


        </section>


        <!-- =================================================
             MESSAGES
        ================================================== -->

        <?php if (
            $success !== ""
        ): ?>

            <div class="alert alert-success">

                <?= h(
                    $success
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (
            $error !== ""
        ): ?>

            <div class="alert alert-error">

                <?= h(
                    $error
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (
            $report
        ): ?>


            <!-- =================================================
                 STATUS
            ================================================== -->

            <div class="status-box">


                <div>

                    <div class="status-label">
                        Report Status
                    </div>

                </div>


                <span
                    class="status <?= h(
                        $statusClass
                    ) ?>"
                >

                    <?= h(
                        $status
                    ) ?>

                </span>


            </div>


            <!-- =================================================
                 STUDENT
            ================================================== -->

            <section class="student-card">


                <?php if (
                    $photoPath !== ""
                ): ?>

                    <img
                        src="<?= h(
                            $photoPath
                        ) ?>"
                        class="student-photo"
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

                    <h2 class="student-name">

                        <?= h(
                            $studentName
                        ) ?>

                    </h2>


                    <div class="student-meta">

                        <span>
                            Student ID:
                            <strong>
                                <?= h(
                                    $report[
                                        "student_number"
                                    ]
                                ) ?>
                            </strong>
                        </span>


                        <span>
                            Class:
                            <strong>
                                <?= h(
                                    $report[
                                        "class_name"
                                    ]
                                ) ?>
                            </strong>
                        </span>


                        <span>
                            Academic Year:
                            <strong>
                                <?= h(
                                    $report[
                                        "academic_year"
                                    ]
                                ) ?>
                            </strong>
                        </span>


                        <span>
                            Term:
                            <strong>
                                <?= h(
                                    $report[
                                        "term_name"
                                    ]
                                ) ?>
                            </strong>
                        </span>

                    </div>

                </div>


            </section>


            <!-- =================================================
                 REPORT FORM
            ================================================== -->

            <form
                method="POST"
                autocomplete="off"
            >


                <!-- =================================================
                     ACADEMIC SUMMARY
                ================================================== -->

                <section class="panel">


                    <div class="panel-header">

                        <h2>
                            Academic Summary
                        </h2>

                        <p>
                            Enter the overall academic performance.
                        </p>

                    </div>


                    <div class="form-body">


                        <div class="form-grid">


                            <div class="field">

                                <label>
                                    Overall Average (%)
                                </label>

                                <input
                                    type="number"
                                    name="average_score"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="<?= h(
                                        $report[
                                            "average_score"
                                        ]
                                        ?? ""
                                    ) ?>"
                                    placeholder="e.g. 78.50"
                                >

                            </div>


                            <div class="field">

                                <label>
                                    Position
                                </label>

                                <input
                                    type="text"
                                    name="position"
                                    value="<?= h(
                                        $report[
                                            "position"
                                        ]
                                        ?? ""
                                    ) ?>"
                                    placeholder="e.g. 5"
                                >

                            </div>


                            <div class="field">

                                <label>
                                    Class Size
                                </label>

                                <input
                                    type="number"
                                    name="class_size"
                                    min="1"
                                    value="<?= h(
                                        $report[
                                            "class_size"
                                        ]
                                        ?? ""
                                    ) ?>"
                                    placeholder="e.g. 32"
                                >

                            </div>


                        </div>


                    </div>


                </section>


                <!-- =================================================
                     SUBJECT RESULTS
                ================================================== -->

                <section class="panel">


                    <div class="panel-header">

                        <h2>
                            Subject Results
                        </h2>

                        <p>
                            Enter the student's subject scores,
                            grades and comments.
                        </p>

                    </div>


                    <?php if (
                        $resultTableExists
                    ): ?>


                        <div class="table-wrap">


                            <table
                                class="results-table"
                            >


                                <thead>

                                <tr>

                                    <th
                                        style="width:28%;"
                                    >
                                        Subject
                                    </th>

                                    <th
                                        style="width:15%;"
                                    >
                                        Score
                                    </th>

                                    <th
                                        style="width:15%;"
                                    >
                                        Grade
                                    </th>

                                    <th>
                                        Comment
                                    </th>

                                </tr>

                                </thead>


                                <tbody>


                                <?php foreach (
                                    $subjects
                                    as $subject
                                ): ?>


                                    <?php

                                    $subjectId =
                                        (int)$subject["id"];


                                    $result =
                                        $resultBySubject[
                                            $subjectId
                                        ]
                                        ?? [];


                                    ?>


                                    <tr>


                                        <td>

                                            <div
                                                class="subject-name"
                                            >

                                                <?= h(
                                                    $subject[
                                                        "subject_name"
                                                    ]
                                                ) ?>

                                            </div>

                                        </td>


                                        <td>

                                            <input
                                                type="number"
                                                name="subject_score[<?= $subjectId ?>]"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                value="<?= h(
                                                    $result[
                                                        "score"
                                                    ]
                                                    ?? ""
                                                ) ?>"
                                            >

                                        </td>


                                        <td>

                                            <input
                                                type="text"
                                                name="subject_grade[<?= $subjectId ?>]"
                                                maxlength="10"
                                                value="<?= h(
                                                    $result[
                                                        "grade"
                                                    ]
                                                    ?? ""
                                                ) ?>"
                                                placeholder="A"
                                            >

                                        </td>


                                        <td>

                                            <input
                                                type="text"
                                                name="subject_comment[<?= $subjectId ?>]"
                                                value="<?= h(
                                                    $result[
                                                        "comment"
                                                    ]
                                                    ?? ""
                                                ) ?>"
                                                placeholder="Subject comment"
                                            >

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                                </tbody>


                            </table>


                        </div>


                    <?php else: ?>


                        <div
                            class="form-body"
                        >

                            <div class="locked">

                                The subject-results table
                                <strong>
                                    report_card_results
                                </strong>
                                has not yet been created in
                                the HIBS database.

                                The overall report information
                                can still be saved, but subject
                                results require that table.

                            </div>

                        </div>


                    <?php endif; ?>


                </section>


                <!-- =================================================
                     ATTENDANCE
                ================================================== -->

                <section class="panel">


                    <div class="panel-header">

                        <h2>
                            Attendance & Conduct
                        </h2>

                        <p>
                            Enter attendance and general
                            behavioural information.
                        </p>

                    </div>


                    <div class="form-body">


                        <div class="form-grid">


                            <div class="field">

                                <label>
                                    Days School Opened
                                </label>

                                <input
                                    type="number"
                                    name="days_opened"
                                    min="0"
                                    value="<?= h(
                                        $report[
                                            "days_opened"
                                        ]
                                        ?? ""
                                    ) ?>"
                                    placeholder="e.g. 120"
                                >

                            </div>


                            <div class="field">

                                <label>
                                    Days Present
                                </label>

                                <input
                                    type="number"
                                    name="days_present"
                                    min="0"
                                    value="<?= h(
                                        $report[
                                            "days_present"
                                        ]
                                        ?? ""
                                    ) ?>"
                                    placeholder="e.g. 114"
                                >

                            </div>


                            <div class="field">

                                <label>
                                    Days Absent
                                </label>

                                <input
                                    type="number"
                                    name="days_absent"
                                    value="<?= h(
                                        $report[
                                            "days_absent"
                                        ]
                                        ?? ""
                                    ) ?>"
                                    readonly
                                >

                            </div>


                            <div class="field">

                                <label>
                                    Conduct
                                </label>

                                <select
                                    name="conduct"
                                >

                                    <option value="">
                                        Select Conduct
                                    </option>

                                    <?php

                                    $conductOptions = [

                                        "Excellent",
                                        "Very Good",
                                        "Good",
                                        "Satisfactory",
                                        "Needs Improvement"

                                    ];

                                    ?>


                                    <?php foreach (
                                        $conductOptions
                                        as $option
                                    ): ?>

                                        <option
                                            value="<?= h(
                                                $option
                                            ) ?>"
                                            <?= (
                                                ($report[
                                                    "conduct"
                                                ] ?? "")
                                                ===
                                                $option
                                            )
                                                ? "selected"
                                                : ""
                                            ?>
                                        >

                                            <?= h(
                                                $option
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="field">

                                <label>
                                    Promotion Status
                                </label>

                                <select
                                    name="promotion_status"
                                >

                                    <option value="">
                                        Select Status
                                    </option>

                                    <?php

                                    $promotionOptions = [

                                        "Promoted",
                                        "Promoted on Trial",
                                        "Not Promoted",
                                        "Graduated"

                                    ];

                                    ?>


                                    <?php foreach (
                                        $promotionOptions
                                        as $option
                                    ): ?>

                                        <option
                                            value="<?= h(
                                                $option
                                            ) ?>"
                                            <?= (
                                                ($report[
                                                    "promotion_status"
                                                ] ?? "")
                                                ===
                                                $option
                                            )
                                                ? "selected"
                                                : ""
                                            ?>
                                        >

                                            <?= h(
                                                $option
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                        </div>


                    </div>


                </section>


                <!-- =================================================
                     COMMENTS
                ================================================== -->

                <section class="panel">


                    <div class="panel-header">

                        <h2>
                            Report Comments
                        </h2>

                        <p>
                            Add the official comments that will
                            appear on the student's report.
                        </p>

                    </div>


                    <div class="form-body">


                        <div class="comments-grid">


                            <div class="field">

                                <label>
                                    Class Teacher's Comment
                                </label>

                                <textarea
                                    name="teacher_comment"
                                    placeholder="Enter the class teacher's comment..."
                                ><?= h(
                                    $report[
                                        "teacher_comment"
                                    ]
                                    ?? ""
                                ) ?></textarea>

                            </div>


                            <div class="field">

                                <label>
                                    Headteacher's Comment
                                </label>

                                <textarea
                                    name="headteacher_comment"
                                    placeholder="Enter the headteacher's comment..."
                                ><?= h(
                                    $report[
                                        "headteacher_comment"
                                    ]
                                    ?? ""
                                ) ?></textarea>

                            </div>


                        </div>


                    </div>


                </section>


                <!-- =================================================
                     ACTIONS
                ================================================== -->

                <section class="panel">


                    <div class="form-actions">


                        <div class="action-left">

                            <a
                                href="reports.php"
                                class="btn btn-cancel"
                            >
                                Cancel
                            </a>

                        </div>


                        <div class="action-right">


                            <?php if (
                                $status === "Draft"
                            ): ?>


                                <button
                                    type="submit"
                                    name="action"
                                    value="save"
                                    class="btn btn-save"
                                >
                                    Save Draft
                                </button>


                                <button
                                    type="submit"
                                    name="action"
                                    value="submit"
                                    class="btn btn-submit"
                                >
                                    Save for Approval
                                </button>


                            <?php else: ?>


                                <button
                                    type="submit"
                                    name="action"
                                    value="save"
                                    class="btn btn-save"
                                >
                                    Save Changes
                                </button>


                            <?php endif; ?>


                        </div>


                    </div>


                </section>


            </form>


        <?php endif; ?>


    </main>


</div>


<script>

/*
|--------------------------------------------------------------------------
| AUTOMATIC ABSENCE CALCULATION
|--------------------------------------------------------------------------
*/

const opened =
    document.querySelector(
        'input[name="days_opened"]'
    );

const present =
    document.querySelector(
        'input[name="days_present"]'
    );

const absent =
    document.querySelector(
        'input[name="days_absent"]'
    );


function calculateAbsence()
{

    if (
        !opened ||
        !present ||
        !absent
    ) {

        return;
    }


    const openedValue =
        parseInt(
            opened.value,
            10
        )
        || 0;


    const presentValue =
        parseInt(
            present.value,
            10
        )
        || 0;


    const difference =
        openedValue -
        presentValue;


    absent.value =
        difference > 0
            ? difference
            : 0;
}


if (opened) {

    opened.addEventListener(
        "input",
        calculateAbsence
    );

}


if (present) {

    present.addEventListener(
        "input",
        calculateAbsence
    );

}


calculateAbsence();

</script>


</body>

</html>
