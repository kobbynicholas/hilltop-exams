<?php

session_start();

require_once "../config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


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


$error = "";

$success = "";


/*
|--------------------------------------------------------------------------
| REVIEW ACTION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $submissionId =
        filter_input(
            INPUT_POST,
            "submission_id",
            FILTER_VALIDATE_INT
        );

    $action =
        $_POST["action"]
        ?? "";

    $comment =
        trim(
            $_POST["comment"]
            ?? ""
        );


    if (!$submissionId) {

        $error =
            "Invalid submission.";

    } else {

        try {

            if (
                $action === "approve"
            ) {

                $stmt =
                    $conn->prepare("
                        UPDATE mark_submissions

                        SET

                            status = 'Approved',

                            reviewed_at = NOW(),

                            reviewed_by = ?,

                            review_comment = ?,

                            updated_at = NOW()

                        WHERE id = ?

                        AND status = 'Submitted'
                    ");

                $stmt->execute([

                    $_SESSION["user_id"],

                    $comment,

                    $submissionId

                ]);


                if (
                    $stmt->rowCount() > 0
                ) {

                    $success =
                        "Marks submission approved.";

                } else {

                    $error =
                        "The submission could not be approved.";
                }


            } elseif (
                $action === "return"
            ) {


                $stmt =
                    $conn->prepare("
                        UPDATE mark_submissions

                        SET

                            status = 'Returned',

                            reviewed_at = NOW(),

                            reviewed_by = ?,

                            review_comment = ?,

                            updated_at = NOW()

                        WHERE id = ?

                        AND status = 'Submitted'
                    ");

                $stmt->execute([

                    $_SESSION["user_id"],

                    $comment,

                    $submissionId

                ]);


                if (
                    $stmt->rowCount() > 0
                ) {

                    $success =
                        "Marks submission returned to the teacher.";

                } else {

                    $error =
                        "The submission could not be returned.";
                }


            } else {

                $error =
                    "Invalid review action.";
            }


        } catch (
            Throwable $e
        ) {

            $error =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD SUBMISSIONS
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT

        ms.*,

        CONCAT_WS(
            ' ',
            u.first_name,
            u.last_name
        ) AS teacher_name,

        c.class_name,

        s.subject_name,

        t.term_name,

        ay.academic_year

    FROM mark_submissions ms

    INNER JOIN teachers te
        ON te.id = ms.teacher_id

    INNER JOIN users u
        ON u.id = te.user_id

    INNER JOIN classes c
        ON c.id = ms.class_id

    INNER JOIN subjects s
        ON s.id = ms.subject_id

    INNER JOIN terms t
        ON t.id = ms.term_id

    INNER JOIN academic_years ay
        ON ay.id = ms.academic_year_id

    ORDER BY

        CASE ms.status

            WHEN 'Submitted' THEN 1
            WHEN 'Returned' THEN 2
            WHEN 'Draft' THEN 3
            WHEN 'Approved' THEN 4

        END,

        ms.updated_at DESC
");


$submissions =
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

    "Submitted" => 0,

    "Returned" => 0,

    "Approved" => 0

];


foreach (
    $submissions
    as $submission
) {

    if (
        isset(
            $counts[
                $submission["status"]
            ]
        )
    ) {

        $counts[
            $submission["status"]
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
    HIBS | Mark Submissions
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


.sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: 235px;

    height: 100vh;

    padding: 27px 17px;

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


.alert {

    margin-bottom: 18px;

    padding: 13px 15px;

    font-size: 9px;

}


.success {

    background: #ebf3ed;

    color: #426b50;

    border:
        1px solid
        #cadccd;

}


.error {

    background: #fbefef;

    color: #8b4b4b;

    border:
        1px solid
        #e1c9c9;

}


.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;

    margin-bottom: 20px;

}


.stat {

    padding: 17px;

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

    font-size: 21px;

    font-weight: 600;

}


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


.table-wrap {

    overflow-x: auto;

}


table {

    width: 100%;

    min-width: 1050px;

    border-collapse: collapse;

}


th {

    padding:
        11px 9px;

    background: #f1f2ef;

    border-bottom:
        1px solid
        #d9d8d3;

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


.status {

    display: inline-block;

    padding:
        6px 8px;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.status-submitted {

    background: #e8eef2;

    color: #4d6875;

}


.status-approved {

    background: #e7f1ea;

    color: #3e6c4d;

}


.status-returned {

    background: #f7eee5;

    color: #856441;

}


.status-draft {

    background: #eeeeec;

    color: #697479;

}


.actions {

    display: flex;

    gap: 5px;

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


.approve {

    background: #506d5b;

    color: #ffffff;

}


.return {

    background: #8a6d4d;

    color: #ffffff;

}


.comment {

    width: 160px;

    height: 30px;

    padding: 6px;

    border:
        1px solid
        #d5d4cf;

    font-family: inherit;

    font-size: 7px;

    resize: vertical;

}


.empty {

    padding:
        60px 20px;

    text-align: center;

    color: #899398;

    font-size: 9px;

}


@media(max-width:900px) {

    .stats {

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
        href="mark_submissions.php"
        class="nav-link active"
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


<div class="main">


<header class="topbar">

    <div class="topbar-title">

        Marks Submission Centre

    </div>

</header>


<main class="content">


<div class="page-title">

    <h1>
        Teacher Mark Submissions
    </h1>

    <p>
        Review, return and approve marks submitted by teachers.
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


<div class="stats">


    <div class="stat">

        <div class="stat-label">
            Draft
        </div>

        <div class="stat-value">
            <?= $counts["Draft"] ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Awaiting Review
        </div>

        <div class="stat-value">
            <?= $counts["Submitted"] ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Returned
        </div>

        <div class="stat-value">
            <?= $counts["Returned"] ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Approved
        </div>

        <div class="stat-value">
            <?= $counts["Approved"] ?>
        </div>

    </div>


</div>


<section class="panel">


<div class="panel-header">

    <h2>
        Submission Register
    </h2>

</div>


<?php if (
    count($submissions)
): ?>


<div class="table-wrap">


<table>


<thead>

<tr>

    <th>
        Teacher
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
        Subject
    </th>

    <th>
        Status
    </th>

    <th>
        Date
    </th>

    <th>
        Review
    </th>

</tr>

</thead>


<tbody>


<?php foreach (
    $submissions
    as $row
): ?>


<tr>


<td>

    <?= h(
        $row["teacher_name"]
    ) ?>

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


<td>

    <?= h(
        $row["subject_name"]
    ) ?>

</td>


<td>


<span
    class="
        status
        status-<?= strtolower(
            $row["status"]
        ) ?>
    "
>

    <?= h(
        $row["status"]
    ) ?>

</span>


</td>


<td>

    <?= h(
        $row["submitted_at"]
        ??
        $row["updated_at"]
    ) ?>

</td>


<td>


<?php if (
    $row["status"]
    ===
    "Submitted"
): ?>


<form
    method="POST"
    class="actions"
>


<input
    type="hidden"
    name="submission_id"
    value="<?= (int)$row["id"] ?>"
>


<textarea
    name="comment"
    class="comment"
    placeholder="Review comment"
></textarea>


<button
    type="submit"
    name="action"
    value="approve"
    class="action approve"
    onclick="
        return confirm(
            'Approve these marks?'
        );
    "
>
    Approve
</button>


<button
    type="submit"
    name="action"
    value="return"
    class="action return"
    onclick="
        return confirm(
            'Return these marks to the teacher?'
        );
    "
>
    Return
</button>


</form>


<?php elseif (
    $row["status"]
    ===
    "Approved"
): ?>


<span
    style="
        color:#4d6c57;
        font-size:7px;
        font-weight:bold;
    "
>
    ✓ APPROVED
</span>


<?php elseif (
    $row["status"]
    ===
    "Returned"
): ?>


<span
    style="
        color:#856441;
        font-size:7px;
        font-weight:bold;
    "
>
    RETURNED
</span>


<?php else: ?>


<span
    style="
        color:#899398;
        font-size:7px;
    "
>
    Awaiting teacher
</span>


<?php endif; ?>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty">

    No teacher mark submissions have been created yet.

</div>


<?php endif; ?>


</section>


</main>


</div>


</body>

</html>
