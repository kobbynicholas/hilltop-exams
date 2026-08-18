<?php

session_start();

require_once "../config/db.php";
require_once "../config/grading.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| REPORT APPROVAL & PUBLISHING CENTRE
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


$adminId =
    (int)$_SESSION["user_id"];


$error = "";

$success = "";


/*
|--------------------------------------------------------------------------
| ACTION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $reportId =
        filter_input(
            INPUT_POST,
            "report_id",
            FILTER_VALIDATE_INT
        );

    $action =
        trim(
            $_POST["action"]
            ?? ""
        );

    $comment =
        trim(
            $_POST["comment"]
            ?? ""
        );


    if (!$reportId) {

        $error =
            "Invalid report.";

    } else {

        try {

            $conn->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | LOAD REPORT
            |--------------------------------------------------------------------------
            */

            $stmt =
                $conn->prepare("
                    SELECT

                        r.*,

                        s.student_id
                            AS student_number,

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
                        ON ay.id =
                            t.academic_year_id

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
                    "Report not found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | APPROVE
            |--------------------------------------------------------------------------
            */

            if (
                $action === "approve"
            ) {


                if (
                    $report[
                        "report_status"
                    ]
                    !==
                    "Draft"
                ) {

                    throw new Exception(
                        "Only Draft reports can be approved."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | VERIFY SUBJECT RESULTS
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $conn->prepare("
                        SELECT

                            COUNT(*) AS total_results,

                            SUM(
                                CASE
                                    WHEN score IS NULL
                                    THEN 1
                                    ELSE 0
                                END
                            ) AS missing_scores

                        FROM report_card_results

                        WHERE report_id = ?
                    ");


                $stmt->execute([
                    $reportId
                ]);


                $resultCheck =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                $totalResults =
                    (int)(
                        $resultCheck[
                            "total_results"
                        ]
                        ?? 0
                    );


                $missingScores =
                    (int)(
                        $resultCheck[
                            "missing_scores"
                        ]
                        ?? 0
                    );


                if (
                    $totalResults === 0
                ) {

                    throw new Exception(
                        "This report has no subject results."
                    );
                }


                if (
                    $missingScores > 0
                ) {

                    throw new Exception(
                        "Some subject results do not have scores."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | CALCULATE AVERAGE
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $conn->prepare("
                        SELECT
                            AVG(score)

                        FROM report_card_results

                        WHERE report_id = ?

                        AND score IS NOT NULL
                    ");


                $stmt->execute([
                    $reportId
                ]);


                $average =
                    $stmt->fetchColumn();


                if (
                    $average === false ||
                    $average === null
                ) {

                    throw new Exception(
                        "Unable to calculate report average."
                    );
                }


                $average =
                    round(
                        (float)$average,
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | CLASS SIZE
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $conn->prepare("
                        SELECT COUNT(*)

                        FROM report_card_records

                        WHERE

                            class_id = ?

                            AND term_id = ?

                            AND report_status !=
                                'Cancelled'
                    ");


                $stmt->execute([

                    $report["class_id"],

                    $report["term_id"]

                ]);


                $classSize =
                    (int)$stmt->fetchColumn();


                /*
                |--------------------------------------------------------------------------
                | UPDATE REPORT
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $conn->prepare("
                        UPDATE report_card_records

                        SET

                            average_score = ?,

                            class_size = ?,

                            report_status =
                                'Approved',

                            updated_at =
                                NOW()

                        WHERE id = ?

                        AND report_status = 'Draft'
                    ");


                $stmt->execute([

                    $average,

                    $classSize,

                    $reportId

                ]);


                if (
                    $stmt->rowCount() === 0
                ) {

                    throw new Exception(
                        "The report could not be approved."
                    );
                }


                $success =
                    "Report approved successfully.";


            /*
            |--------------------------------------------------------------------------
            | RETURN
            |--------------------------------------------------------------------------
            */

            } elseif (
                $action === "return"
            ) {


                if (
                    $report[
                        "report_status"
                    ]
                    !==
                    "Draft"
                ) {

                    throw new Exception(
                        "Only Draft reports can be returned."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | RETURN TO DRAFT
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $conn->prepare("
                        UPDATE report_card_records

                        SET

                            teacher_comment =
                                CASE

                                    WHEN ?
                                        = ''

                                    THEN teacher_comment

                                    ELSE CONCAT(
                                        COALESCE(
                                            teacher_comment,
                                            ''
                                        ),

                                        CASE
                                            WHEN
                                                COALESCE(
                                                    teacher_comment,
                                                    ''
                                                ) = ''

                                            THEN ''

                                            ELSE '\n\n'
                                        END,

                                        'ADMIN REVIEW: ',

                                        ?
                                    )

                                END,

                            updated_at =
                                NOW()

                        WHERE id = ?

                        AND report_status = 'Draft'
                    ");


                $stmt->execute([

                    $comment,

                    $comment,

                    $reportId

                ]);


                $success =
                    "Report returned for correction.";


            /*
            |--------------------------------------------------------------------------
            | PUBLISH
            |--------------------------------------------------------------------------
            */

            } elseif (
                $action === "publish"
            ) {


                if (
                    $report[
                        "report_status"
                    ]
                    !==
                    "Approved"
                ) {

                    throw new Exception(
                        "Only Approved reports can be published."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | PUBLISH
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $conn->prepare("
                        UPDATE report_card_records

                        SET

                            report_status =
                                'Published',

                            published_at =
                                NOW(),

                            updated_at =
                                NOW()

                        WHERE id = ?

                        AND report_status =
                            'Approved'
                    ");


                $stmt->execute([
                    $reportId
                ]);


                if (
                    $stmt->rowCount() === 0
                ) {

                    throw new Exception(
                        "The report could not be published."
                    );
                }


                $success =
                    "Report published successfully. It is now locked.";


            /*
            |--------------------------------------------------------------------------
            | UNPUBLISH
            |--------------------------------------------------------------------------
            */

            } elseif (
                $action === "unpublish"
            ) {


                /*
                |--------------------------------------------------------------------------
                | EXTRA PROTECTION
                |--------------------------------------------------------------------------
                */

                $confirm =
                    $_POST["confirm"]
                    ?? "";


                if (
                    $confirm !==
                    "UNPUBLISH"
                ) {

                    throw new Exception(
                        "Unpublish confirmation was not provided."
                    );
                }


                if (
                    $report[
                        "report_status"
                    ]
                    !==
                    "Published"
                ) {

                    throw new Exception(
                        "Only Published reports can be unpublished."
                    );
                }


                $stmt =
                    $conn->prepare("
                        UPDATE report_card_records

                        SET

                            report_status =
                                'Approved',

                            published_at =
                                NULL,

                            updated_at =
                                NOW()

                        WHERE id = ?

                        AND report_status =
                            'Published'
                    ");


                $stmt->execute([
                    $reportId
                ]);


                if (
                    $stmt->rowCount() === 0
                ) {

                    throw new Exception(
                        "The report could not be unpublished."
                    );
                }


                $success =
                    "Report unpublished and returned to Approved status.";


            } else {

                throw new Exception(
                    "Invalid action."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $conn->commit();


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
}


/*
|--------------------------------------------------------------------------
| LOAD REPORTS
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->query("
        SELECT

            r.id,

            r.average_score,

            r.position,

            r.class_size,

            r.report_status,

            r.published_at,

            r.updated_at,

            s.student_id
                AS student_number,

            CONCAT_WS(
                ' ',
                s.first_name,
                s.middle_name,
                s.last_name
            ) AS student_name,

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
            ON ay.id =
                t.academic_year_id

        ORDER BY

            CASE
                WHEN r.report_status =
                    'Draft'
                THEN 1

                WHEN r.report_status =
                    'Approved'
                THEN 2

                WHEN r.report_status =
                    'Published'
                THEN 3

                ELSE 4
            END,

            r.updated_at DESC
    ");


$reports =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$counts = [

    "Draft" => 0,

    "Approved" => 0,

    "Published" => 0

];


foreach (
    $reports
    as $report
) {

    if (
        isset(
            $counts[
                $report["report_status"]
            ]
        )
    ) {

        $counts[
            $report["report_status"]
        ]++;
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

<title>
    HIBS | Report Approval
</title>


<style>

* {
    box-sizing: border-box;
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

    top: 0;
    left: 0;

    width: 235px;

    height: 100vh;

    padding:
        27px 17px;

    background: #263238;

    color: #ffffff;

}


.brand {

    padding:
        3px 10px 25px;

    margin-bottom: 20px;

    border-bottom:
        1px solid
        rgba(255,255,255,.12);

}


.brand-title {

    font-size: 17px;

    font-weight: 700;

    letter-spacing: 1px;

}


.brand-subtitle {

    margin-top: 6px;

    color: #aeb8bc;

    font-size: 8px;

    line-height: 1.6;

}


.nav-title {

    padding:
        0 10px 7px;

    color: #879399;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.nav-link {

    display: block;

    padding: 11px;

    margin-bottom: 4px;

    color: #dce2e5;

    text-decoration: none;

    font-size: 10px;

    border-radius: 4px;

}


.nav-link:hover {

    background: #37474f;

}


.nav-link.active {

    background: #546e7a;

}


.logout {

    position: absolute;

    left: 17px;
    right: 17px;
    bottom: 20px;

    padding: 10px;

    color: #dce2e5;

    text-decoration: none;

    border:
        1px solid
        rgba(255,255,255,.15);

    text-align: center;

    font-size: 9px;

    border-radius: 4px;

}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 235px;

    min-height: 100vh;

}


.topbar {

    height: 70px;

    padding:
        0 32px;

    background: #ffffff;

    border-bottom:
        1px solid
        #deddd8;

    display: flex;

    align-items: center;

}


.topbar-title {

    font-size: 16px;

    font-weight: 600;

}


.content {

    padding:
        28px 32px;

    max-width: 1500px;

}


/* =========================================================
   TITLE
========================================================= */

.page-title {

    margin-bottom: 22px;

}


.page-title h1 {

    margin: 0;

    font-size: 24px;

    font-weight: 600;

}


.page-title p {

    margin:
        7px 0 0;

    color: #7d898d;

    font-size: 9px;

}


/* =========================================================
   ALERT
========================================================= */

.alert {

    margin-bottom: 18px;

    padding:
        13px 15px;

    font-size: 9px;

    border: 1px solid;

}


.success {

    background: #eaf3ed;

    border-color: #cbdccd;

    color: #426b50;

}


.error {

    background: #fbefef;

    border-color: #e0c8c8;

    color: #8b4b4b;

}


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 12px;

    margin-bottom: 20px;

}


.stat {

    padding: 18px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.stat-label {

    color: #7b878b;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.stat-value {

    margin-top: 7px;

    font-size: 22px;

    font-weight: 600;

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
        18px 20px;

    border-bottom:
        1px solid
        #e7e5e1;

}


.panel-header h2 {

    margin: 0;

    font-size: 14px;

    font-weight: 600;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrap {

    overflow-x: auto;

}


table {

    width: 100%;

    min-width: 1100px;

    border-collapse: collapse;

}


th {

    padding:
        11px 9px;

    background: #f1f2ef;

    border-bottom:
        1px solid
        #d8d7d2;

    color: #68767b;

    font-size: 7px;

    text-align: left;

    text-transform: uppercase;

}


td {

    padding:
        11px 9px;

    border-bottom:
        1px solid
        #eceae6;

    color: #455a64;

    font-size: 8px;

    vertical-align: middle;

}


.student-name {

    font-weight: 600;

}


.student-id {

    margin-top: 3px;

    color: #98a1a4;

    font-size: 6px;

}


.average {

    font-weight: 700;

}


.position {

    text-align: center;

}


.status {

    display: inline-block;

    padding:
        6px 8px;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.status-draft {

    background: #eeeeec;

    color: #68767b;

}


.status-approved {

    background: #e8f0eb;

    color: #466b53;

}


.status-published {

    background: #e5edf0;

    color: #496672;

}


.actions {

    display: flex;

    gap: 5px;

    align-items: center;

    flex-wrap: wrap;

}


.action {

    padding:
        7px 9px;

    border: 0;

    border-radius: 3px;

    font-family: inherit;

    font-size: 7px;

    font-weight: bold;

    cursor: pointer;

}


.view {

    background: #edf0f1;

    color: #53656c;

}


.approve {

    background: #506d5b;

    color: #ffffff;

}


.return {

    background: #896d4e;

    color: #ffffff;

}


.publish {

    background: #455a64;

    color: #ffffff;

}


.unpublish {

    background: #7c5e5e;

    color: #ffffff;

}


.empty {

    padding:
        60px 20px;

    color: #899398;

    font-size: 9px;

    text-align: center;

}


.comment {

    width: 140px;

    height: 30px;

    padding: 6px;

    border:
        1px solid
        #d5d4cf;

    font-family: inherit;

    font-size: 7px;

    resize: vertical;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:800px) {

    .stats {

        grid-template-columns:
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


    .nav-title {

        display: none;

    }


    .nav-link {

        display: inline-block;

        padding: 8px;

    }


    .logout {

        position: static;

        margin-top: 12px;

    }


    .main {

        margin-left: 0;

    }


    .content {

        padding:
            20px 15px;

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
    Report Approval
</a>


<a
    href="mark_submissions.php"
    class="nav-link"
>
    Mark Submissions
</a>


<a
    href="students.php"
    class="nav-link"
>
    Students
</a>


<a
    href="teachers.php"
    class="nav-link"
>
    Teachers
</a>


<a
    href="database_check.php"
    class="nav-link"
>
    Database Check
</a>


<a
    href="../logout.php"
    class="logout"
>
    Sign Out
</a>


</aside>


<!-- =====================================================
     MAIN
====================================================== -->

<div class="main">


<header class="topbar">

    <div class="topbar-title">

        Report Approval & Publishing

    </div>

</header>


<main class="content">


<div class="page-title">

    <h1>
        Report Approval Centre
    </h1>

    <p>
        Review, approve and publish student academic reports.
    </p>

</div>


<?php if (
    $success
): ?>

<div class="alert success">

    <?= h(
        $success
    ) ?>

</div>

<?php endif; ?>


<?php if (
    $error
): ?>

<div class="alert error">

    <?= h(
        $error
    ) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     STATISTICS
====================================================== -->

<div class="stats">


<div class="stat">

    <div class="stat-label">
        Draft Reports
    </div>

    <div class="stat-value">
        <?= $counts["Draft"] ?>
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Approved Reports
    </div>

    <div class="stat-value">
        <?= $counts["Approved"] ?>
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Published Reports
    </div>

    <div class="stat-value">
        <?= $counts["Published"] ?>
    </div>

</div>


</div>


<!-- =====================================================
     REPORT REGISTER
====================================================== -->

<section class="panel">


<div class="panel-header">

    <h2>
        Report Register
    </h2>

</div>


<?php if (
    count($reports)
): ?>


<div class="table-wrap">


<table>


<thead>

<tr>

    <th>
        Student
    </th>

    <th>
        Academic Year
    </th>

    <th>
        Term
    </th>

    <th>
        Class
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
        Actions
    </th>

</tr>

</thead>


<tbody>


<?php foreach (
    $reports
    as $row
): ?>


<tr>


<td>

<div class="student-name">

    <?= h(
        $row["student_name"]
    ) ?>

</div>


<div class="student-id">

    <?= h(
        $row["student_number"]
    ) ?>

</div>

</td>


<td>

    <?= h(
        $row["academic_year"]
    ) ?>

</td>


<td>

    <?= h(
        $row["term_name"]
    ) ?>

</td>


<td>

    <?= h(
        $row["class_name"]
    ) ?>

</td>


<td class="average">

<?php if (
    $row["average_score"]
    !==
    null
): ?>

    <?= number_format(
        (float)$row[
            "average_score"
        ],
        2
    ) ?>%

<?php else: ?>

    —

<?php endif; ?>

</td>


<td class="position">

<?php if (
    $row["position"]
    !== null
): ?>

    <?= h(
        $row["position"]
    ) ?>

    <?php if (
        $row["class_size"]
    ): ?>

        /
        <?= h(
            $row["class_size"]
        ) ?>

    <?php endif; ?>

<?php else: ?>

    —

<?php endif; ?>

</td>


<td>


<span
    class="
        status
        status-<?=
            strtolower(
                $row["report_status"]
            )
        ?>
    "
>

    <?= h(
        $row["report_status"]
    ) ?>

</span>


</td>


<td>


<div class="actions">


<!-- VIEW -->

<a
    href="../student_report.php?id=<?= (int)$row["id"] ?>"
    target="_blank"
    class="action view"
>
    View
</a>


<!-- APPROVE -->

<?php if (
    $row["report_status"]
    ===
    "Draft"
): ?>


<form
    method="POST"
    style="display:inline"
>


<input
    type="hidden"
    name="report_id"
    value="<?= (int)$row["id"] ?>"
>


<input
    type="hidden"
    name="action"
    value="approve"
>


<button
    type="submit"
    class="action approve"
    onclick="
        return confirm(
            'Approve this report?'
        );
    "
>

    Approve

</button>


</form>


<!-- RETURN -->

<form
    method="POST"
    style="display:inline"
>


<input
    type="hidden"
    name="report_id"
    value="<?= (int)$row["id"] ?>"
>


<input
    type="hidden"
    name="action"
    value="return"
>


<input
    type="hidden"
    name="comment"
    value=""
>


<button
    type="submit"
    class="action return"
    onclick="
        const note =
        prompt(
            'Enter the correction required:'
        );

        if (
            note === null
            ||
            note.trim() === ''
        ) {
            return false;
        }

        this.form.comment.value =
            note;

        return true;
    "
>

    Return

</button>


</form>


<?php endif; ?>


<!-- PUBLISH -->

<?php if (
    $row["report_status"]
    ===
    "Approved"
): ?>


<form
    method="POST"
    style="display:inline"
>


<input
    type="hidden"
    name="report_id"
    value="<?= (int)$row["id"] ?>"
>


<input
    type="hidden"
    name="action"
    value="publish"
>


<button
    type="submit"
    class="action publish"
    onclick="
        return confirm(
            'Publish this report? Once published, it will be locked and made available to the student.'
        );
    "
>

    Publish

</button>


</form>


<?php endif; ?>


<!-- UNPUBLISH -->

<?php if (
    $row["report_status"]
    ===
    "Published"
): ?>


<form
    method="POST"
    style="display:inline"
>


<input
    type="hidden"
    name="report_id"
    value="<?= (int)$row["id"] ?>"
>


<input
    type="hidden"
    name="action"
    value="unpublish"
>


<input
    type="hidden"
    name="confirm"
    value="UNPUBLISH"
>


<button
    type="submit"
    class="action unpublish"
    onclick="
        return confirm(
            'WARNING: Unpublishing will remove this report from the student published-report view. Continue?'
        );
    "
>

    Unpublish

</button>


</form>


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

    No academic reports have been created yet.

</div>


<?php endif; ?>


</section>


</main>


</div>


</body>

</html>
