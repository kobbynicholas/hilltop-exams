<?php

session_start();

require_once "../config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| STUDENT REPORT CENTRE
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


$userId =
    (int)$_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| FIND STUDENT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        id,
        student_id,
        first_name,
        middle_name,
        last_name,
        photo

    FROM students

    WHERE user_id = ?

    LIMIT 1
");

$stmt->execute([
    $userId
]);

$student =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| FALLBACK
|--------------------------------------------------------------------------
|
| Some HIBS installations may use students.id
| directly in the session.
|
*/

if (!$student) {

    $sessionStudentId =
        $_SESSION["student_id"]
        ?? null;


    if ($sessionStudentId) {

        $stmt = $conn->prepare("
            SELECT

                id,
                student_id,
                first_name,
                middle_name,
                last_name,
                photo

            FROM students

            WHERE id = ?

            LIMIT 1
        ");

        $stmt->execute([
            (int)$sessionStudentId
        ]);

        $student =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );
    }
}


if (!$student) {

    die(
        "Student profile could not be found."
    );
}


$studentDatabaseId =
    (int)$student["id"];


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


/*
|--------------------------------------------------------------------------
| ACADEMIC YEARS WITH PUBLISHED REPORTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT DISTINCT

        ay.id,
        ay.academic_year

    FROM report_card_records r

    INNER JOIN terms t
        ON t.id = r.term_id

    INNER JOIN academic_years ay
        ON ay.id = t.academic_year_id

    WHERE

        r.student_id = ?

        AND r.report_status = 'Published'

    ORDER BY ay.id DESC
");

$stmt->execute([
    $studentDatabaseId
]);

$academicYears =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| TERMS
|--------------------------------------------------------------------------
*/

$terms = [];


if (
    $academicYearId
) {

    $stmt = $conn->prepare("
        SELECT DISTINCT

            t.id,
            t.term_name

        FROM report_card_records r

        INNER JOIN terms t
            ON t.id = r.term_id

        WHERE

            r.student_id = ?

            AND t.academic_year_id = ?

            AND r.report_status = 'Published'

        ORDER BY t.id ASC
    ");

    $stmt->execute([

        $studentDatabaseId,

        $academicYearId

    ]);

    $terms =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


/*
|--------------------------------------------------------------------------
| LOAD REPORTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        r.id,

        r.average_score,

        r.position,

        r.class_size,

        r.report_status,

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
";


$params = [
    $studentDatabaseId
];


if (
    $academicYearId
) {

    $sql .= "
        AND ay.id = ?
    ";

    $params[] =
        $academicYearId;
}


if (
    $termId
) {

    $sql .= "
        AND t.id = ?
    ";

    $params[] =
        $termId;
}


$sql .= "
    ORDER BY

        ay.id DESC,

        t.id ASC,

        r.id DESC
";


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


/*
|--------------------------------------------------------------------------
| STUDENT NAME
|--------------------------------------------------------------------------
*/

$studentName =
    trim(
        implode(
            " ",
            array_filter([

                $student[
                    "first_name"
                ] ?? "",

                $student[
                    "middle_name"
                ] ?? "",

                $student[
                    "last_name"
                ] ?? ""

            ])
        )
    );


/*
|--------------------------------------------------------------------------
| PHOTO
|--------------------------------------------------------------------------
*/

$photoPath = "";


if (
    !empty(
        $student["photo"]
    )
) {

    $possiblePhotos = [

        "../uploads/students/" .
        $student["photo"],

        "../uploads/" .
        $student["photo"],

        "../assets/uploads/students/" .
        $student["photo"]

    ];


    foreach (
        $possiblePhotos
        as $photo
    ) {

        if (
            is_file(
                __DIR__ .
                "/" .
                $photo
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

<title>
    HIBS | My Reports
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

    letter-spacing: .7px;

}


.student-mini {

    display: flex;

    align-items: center;

    gap: 9px;

    padding:
        0 8px 20px;

}


.student-mini-photo {

    width: 34px;

    height: 34px;

    border-radius: 50%;

    object-fit: cover;

    border:
        1px solid
        rgba(255,255,255,.2);

}


.student-mini-placeholder {

    width: 34px;

    height: 34px;

    border-radius: 50%;

    background: #455a64;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 8px;

    font-weight: bold;

}


.student-mini-name {

    max-width: 145px;

    color: #ffffff;

    font-size: 8px;

    font-weight: 600;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}


.student-mini-id {

    margin-top: 3px;

    color: #9ba7ab;

    font-size: 6px;

}


.nav-title {

    padding:
        0 10px 7px;

    color: #879399;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: 1px;

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

    justify-content: space-between;

}


.topbar-title {

    font-size: 16px;

    font-weight: 600;

}


.topbar-right {

    color: #7b878b;

    font-size: 8px;

}


/* =========================================================
   CONTENT
========================================================= */

.content {

    max-width: 1250px;

    padding:
        28px 32px;

}


/* =========================================================
   PAGE TITLE
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
   PROFILE CARD
========================================================= */

.profile-card {

    margin-bottom: 20px;

    padding: 18px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    display: flex;

    align-items: center;

    gap: 14px;

}


.profile-photo {

    width: 58px;

    height: 58px;

    object-fit: cover;

    border-radius: 50%;

    border:
        1px solid
        #d4d3ce;

}


.profile-placeholder {

    width: 58px;

    height: 58px;

    border-radius: 50%;

    background: #ececea;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #7c898d;

    font-size: 9px;

    font-weight: bold;

}


.profile-name {

    font-size: 15px;

    font-weight: 600;

}


.profile-id {

    margin-top: 5px;

    color: #7f8b8f;

    font-size: 8px;

}


/* =========================================================
   FILTER
========================================================= */

.filter-panel {

    margin-bottom: 20px;

    padding: 18px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.filter-title {

    margin-bottom: 13px;

    font-size: 12px;

    font-weight: 600;

}


.filters {

    display: grid;

    grid-template-columns:
        1fr
        1fr
        auto;

    gap: 10px;

    align-items: end;

}


.field label {

    display: block;

    margin-bottom: 6px;

    color: #68767b;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.field select {

    width: 100%;

    height: 37px;

    padding:
        0 10px;

    border:
        1px solid
        #d2d1cc;

    background: #ffffff;

    color: #455a64;

    font-family: inherit;

    font-size: 8px;

    border-radius: 3px;

}


.filter-button {

    height: 37px;

    padding:
        0 15px;

    border: 0;

    border-radius: 3px;

    background: #455a64;

    color: #ffffff;

    font-family: inherit;

    font-size: 8px;

    font-weight: bold;

    cursor: pointer;

}


.clear-button {

    display: inline-flex;

    height: 37px;

    padding:
        0 13px;

    align-items: center;

    justify-content: center;

    background: #ffffff;

    border:
        1px solid
        #d2d1cc;

    border-radius: 3px;

    color: #68767b;

    text-decoration: none;

    font-size: 8px;

}


/* =========================================================
   REPORTS
========================================================= */

.section-title {

    margin-bottom: 11px;

    font-size: 13px;

    font-weight: 600;

}


.report-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 14px;

}


.report-card {

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.report-card-header {

    padding: 16px;

    border-bottom:
        1px solid
        #e7e5e1;

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 10px;

}


.report-term {

    font-size: 12px;

    font-weight: 600;

}


.report-year {

    margin-top: 4px;

    color: #7e898d;

    font-size: 7px;

}


.published {

    padding:
        5px 7px;

    background: #e8f0eb;

    color: #496b55;

    font-size: 6px;

    font-weight: bold;

    text-transform: uppercase;

}


.report-body {

    padding: 16px;

}


.info-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 8px;

}


.info {

    padding: 10px;

    background: #f6f6f3;

    text-align: center;

}


.info-label {

    color: #818c90;

    font-size: 6px;

    font-weight: bold;

    text-transform: uppercase;

}


.info-value {

    margin-top: 5px;

    color: #37474f;

    font-size: 13px;

    font-weight: 700;

}


.report-actions {

    margin-top: 14px;

    display: flex;

    gap: 7px;

}


.action {

    flex: 1;

    height: 34px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 3px;

    text-decoration: none;

    font-size: 7px;

    font-weight: bold;

}


.view {

    background: #455a64;

    color: #ffffff;

}


.print {

    background: #ffffff;

    border:
        1px solid
        #d1d0cb;

    color: #5d6c72;

}


.empty {

    padding:
        65px 20px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

    text-align: center;

}


.empty-title {

    color: #526269;

    font-size: 13px;

    font-weight: 600;

}


.empty-text {

    max-width: 420px;

    margin:
        8px auto 0;

    color: #899398;

    font-size: 8px;

    line-height: 1.7;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

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


    .filters {

        grid-template-columns: 1fr;

    }


    .profile-card {

        align-items: flex-start;

    }


    .info-grid {

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


<div class="student-mini">


<?php if (
    $photoPath
): ?>

    <img
        src="<?= h(
            $photoPath
        ) ?>"
        class="student-mini-photo"
        alt="Student"
    >

<?php else: ?>

    <div class="student-mini-placeholder">

        <?= h(
            strtoupper(
                substr(
                    $studentName,
                    0,
                    1
                )
            )
        ) ?>

    </div>

<?php endif; ?>


<div>

    <div class="student-mini-name">

        <?= h(
            $studentName
        ) ?>

    </div>

    <div class="student-mini-id">

        <?= h(
            $student[
                "student_id"
            ]
        ) ?>

    </div>

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

    My Academic Reports

</div>


<div class="topbar-right">

    <?= h(
        $student[
            "student_id"
        ]
    ) ?>

</div>


</header>


<main class="content">


<!-- PAGE TITLE -->

<div class="page-title">

    <h1>
        Academic Reports
    </h1>

    <p>
        View your officially published academic reports.
    </p>

</div>


<!-- PROFILE -->

<div class="profile-card">


<?php if (
    $photoPath
): ?>

    <img
        src="<?= h(
            $photoPath
        ) ?>"
        class="profile-photo"
        alt="Student"
    >

<?php else: ?>

    <div class="profile-placeholder">

        <?= h(
            strtoupper(
                substr(
                    $studentName,
                    0,
                    1
                )
            )
        ) ?>

    </div>

<?php endif; ?>


<div>

    <div class="profile-name">

        <?= h(
            $studentName
        ) ?>

    </div>


    <div class="profile-id">

        Student ID:

        <?= h(
            $student[
                "student_id"
            ]
        ) ?>

    </div>

</div>


</div>


<!-- FILTER -->

<section class="filter-panel">


<div class="filter-title">

    Find a Report

</div>


<form
    method="GET"
>


<div class="filters">


<div class="field">

<label>
    Academic Year
</label>


<select
    name="academic_year_id"
    onchange="
        document
        .getElementById(
            'filterForm'
        )
        .submit();
    "
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
        (int)$academicYearId
        ===
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
        (int)$termId
        ===
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


<div>

<button
    type="submit"
    class="filter-button"
>
    Filter Reports
</button>

</div>


</div>


</form>


<?php if (
    $academicYearId ||
    $termId
): ?>

<br>


<a
    href="reports.php"
    class="clear-button"
>
    Clear Filters
</a>


<?php endif; ?>


</section>


<!-- REPORT LIST -->

<div class="section-title">

    Published Reports

</div>


<?php if (
    count($reports) > 0
): ?>


<div class="report-grid">


<?php foreach (
    $reports
    as $report
): ?>


<article class="report-card">


<div class="report-card-header">


<div>

    <div class="report-term">

        <?= h(
            $report[
                "term_name"
            ]
        ) ?>

    </div>


    <div class="report-year">

        <?= h(
            $report[
                "academic_year"
            ]
        ) ?>

        &nbsp; • &nbsp;

        <?= h(
            $report[
                "class_name"
            ]
        ) ?>

    </div>

</div>


<div class="published">

    Published

</div>


</div>


<div class="report-body">


<div class="info-grid">


<div class="info">

    <div class="info-label">
        Average
    </div>

    <div class="info-value">

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

    </div>

</div>


<div class="info">

    <div class="info-label">
        Position
    </div>

    <div class="info-value">

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

        <?php else: ?>

            —

        <?php endif; ?>

    </div>

</div>


<div class="info">

    <div class="info-label">
        Class Size
    </div>

    <div class="info-value">

        <?= h(
            $report[
                "class_size"
            ]
            ??
            "—"
        ) ?>

    </div>

</div>


</div>


<div class="report-actions">


<a
    href="print_report.php?report_id=<?= (int)$report["id"] ?>"
    target="_blank"
>
    View / Print
</a>


<a
    href="../student_report_print.php?id=<?= (int)$report["id"] ?>"
    class="action print"
    target="_blank"
    rel="noopener"
>
    Print / Save PDF
</a>


</div>


</div>


</article>


<?php endforeach; ?>


</div>


<?php else: ?>


<div class="empty">


<div class="empty-title">

    No Published Reports

</div>


<div class="empty-text">

    Your academic reports will appear here once
    the school has completed the review and
    officially published them.

</div>


</div>


<?php endif; ?>


</main>


</div>


<script>

const filterForm =
    document.querySelector(
        "form"
    );


</script>


</body>

</html>
