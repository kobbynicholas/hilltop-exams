<?php

session_start();

require_once "../config/db.php";

ini_set("display_errors", "0");
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| ADMIN ACADEMIC ANALYTICS
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

$terms = [];

if (
    $academicYearId
) {

    $stmt =
        $conn->prepare("
            SELECT

                id,
                term_name

            FROM terms

            WHERE academic_year_id = ?

            ORDER BY id ASC
        ");

    $stmt->execute([
        $academicYearId
    ]);

    $terms =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


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
| BASE FILTER
|--------------------------------------------------------------------------
*/

$where = [

    "r.report_status = 'Published'"

];


$params = [];


if (
    $academicYearId
) {

    $where[] =
        "ay.id = ?";

    $params[] =
        $academicYearId;
}


if (
    $termId
) {

    $where[] =
        "t.id = ?";

    $params[] =
        $termId;
}


if (
    $classId
) {

    $where[] =
        "r.class_id = ?";

    $params[] =
        $classId;
}


$whereSql =
    implode(
        " AND ",
        $where
    );


/*
|--------------------------------------------------------------------------
| OVERALL STATISTICS
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT

            COUNT(
                DISTINCT r.student_id
            ) AS students,

            AVG(
                r.average_score
            ) AS average_score,

            MAX(
                r.average_score
            ) AS highest_score,

            MIN(
                r.average_score
            ) AS lowest_score

        FROM report_card_records r

        INNER JOIN terms t
            ON t.id = r.term_id

        INNER JOIN academic_years ay
            ON ay.id =
                t.academic_year_id

        WHERE
            $whereSql
    ");

$stmt->execute(
    $params
);

$overall =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


$totalStudents =
    (int)(
        $overall[
            "students"
        ]
        ?? 0
    );


$schoolAverage =
    (float)(
        $overall[
            "average_score"
        ]
        ?? 0
    );


$highestScore =
    (float)(
        $overall[
            "highest_score"
        ]
        ?? 0
    );


$lowestScore =
    (float)(
        $overall[
            "lowest_score"
        ]
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| HIGHEST STUDENT
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT

            CONCAT_WS(
                ' ',
                s.first_name,
                s.middle_name,
                s.last_name
            ) AS student_name,

            s.student_id,

            r.average_score,

            c.class_name

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

        WHERE
            $whereSql

        ORDER BY
            r.average_score DESC

        LIMIT 1
    ");

$stmt->execute(
    $params
);

$highestStudent =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| LOWEST STUDENT
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT

            CONCAT_WS(
                ' ',
                s.first_name,
                s.middle_name,
                s.last_name
            ) AS student_name,

            s.student_id,

            r.average_score,

            c.class_name

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

        WHERE
            $whereSql

        ORDER BY
            r.average_score ASC

        LIMIT 1
    ");

$stmt->execute(
    $params
);

$lowestStudent =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| CLASS PERFORMANCE
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT

            c.class_name,

            COUNT(
                DISTINCT r.student_id
            ) AS students,

            ROUND(
                AVG(
                    r.average_score
                ),
                2
            ) AS average_score,

            MAX(
                r.average_score
            ) AS highest_score,

            MIN(
                r.average_score
            ) AS lowest_score

        FROM report_card_records r

        INNER JOIN classes c
            ON c.id = r.class_id

        INNER JOIN terms t
            ON t.id = r.term_id

        INNER JOIN academic_years ay
            ON ay.id =
                t.academic_year_id

        WHERE
            $whereSql

        GROUP BY

            r.class_id,
            c.class_name

        ORDER BY
            average_score DESC
    ");

$stmt->execute(
    $params
);

$classPerformance =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| SUBJECT PERFORMANCE
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT

            s.subject_name,

            COUNT(
                mr.id
            ) AS entries,

            ROUND(
                AVG(
                    mr.score
                ),
                2
            ) AS average_score,

            MAX(
                mr.score
            ) AS highest_score,

            MIN(
                mr.score
            ) AS lowest_score

        FROM report_card_results mr

        INNER JOIN report_card_records r
            ON r.id = mr.report_id

        INNER JOIN subjects s
            ON s.id = mr.subject_id

        INNER JOIN terms t
            ON t.id = r.term_id

        INNER JOIN academic_years ay
            ON ay.id =
                t.academic_year_id

        WHERE
            $whereSql

        GROUP BY

            mr.subject_id,
            s.subject_name

        ORDER BY
            average_score DESC
    ");

$stmt->execute(
    $params
);

$subjectPerformance =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| GRADE DISTRIBUTION
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT

            mr.grade,

            COUNT(*) AS total

        FROM report_card_results mr

        INNER JOIN report_card_records r
            ON r.id = mr.report_id

        INNER JOIN terms t
            ON t.id = r.term_id

        INNER JOIN academic_years ay
            ON ay.id =
                t.academic_year_id

        WHERE
            $whereSql

        AND mr.grade IS NOT NULL

        AND mr.grade <> ''

        GROUP BY
            mr.grade

        ORDER BY
            total DESC
    ");

$stmt->execute(
    $params
);

$grades =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| STUDENT RANKING
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare("
        SELECT

            CONCAT_WS(
                ' ',
                s.first_name,
                s.middle_name,
                s.last_name
            ) AS student_name,

            s.student_id,

            c.class_name,

            r.average_score,

            r.position,

            r.class_size

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

        WHERE
            $whereSql

        ORDER BY

            r.average_score DESC,

            student_name ASC

        LIMIT 20
    ");

$stmt->execute(
    $params
);

$rankings =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| SELECTED NAMES
|--------------------------------------------------------------------------
*/

$selectedYearName =
    "All Academic Years";


foreach (
    $academicYears
    as $year
) {

    if (
        (int)$year["id"]
        ===
        (int)$academicYearId
    ) {

        $selectedYearName =
            $year[
                "academic_year"
            ];

        break;
    }
}


$selectedTermName =
    "All Terms";


foreach (
    $terms
    as $term
) {

    if (
        (int)$term["id"]
        ===
        (int)$termId
    ) {

        $selectedTermName =
            $term[
                "term_name"
            ];

        break;
    }
}


$selectedClassName =
    "All Classes";


foreach (
    $classes
    as $class
) {

    if (
        (int)$class["id"]
        ===
        (int)$classId
    ) {

        $selectedClassName =
            $class[
                "class_name"
            ];

        break;
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
    HIBS | Academic Analytics
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

    left: 0;
    top: 0;

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

    max-width: 1500px;

    padding:
        28px 32px;

}


/* =========================================================
   TITLE
========================================================= */

.page-title {

    margin-bottom: 20px;

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
   FILTER
========================================================= */

.filter {

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


.filter-grid {

    display: grid;

    grid-template-columns:
        1fr
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
        0 9px;

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
        0 16px;

    border: 0;

    border-radius: 3px;

    background: #455a64;

    color: #ffffff;

    font-family: inherit;

    font-size: 8px;

    font-weight: bold;

    cursor: pointer;

}


/* =========================================================
   FILTER SUMMARY
========================================================= */

.filter-summary {

    margin-bottom: 20px;

    color: #7c898d;

    font-size: 8px;

}


.filter-summary strong {

    color: #455a64;

}


/* =========================================================
   STAT CARDS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

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

    margin-top: 8px;

    color: #37474f;

    font-size: 22px;

    font-weight: 600;

}


.stat-note {

    margin-top: 5px;

    color: #929b9e;

    font-size: 6px;

}


/* =========================================================
   HIGHLIGHTS
========================================================= */

.highlights {

    display: grid;

    grid-template-columns:
        1fr
        1fr;

    gap: 12px;

    margin-bottom: 20px;

}


.highlight {

    padding: 18px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.highlight-title {

    margin-bottom: 12px;

    color: #68767b;

    font-size: 7px;

    font-weight: bold;

    text-transform: uppercase;

}


.highlight-name {

    font-size: 14px;

    font-weight: 600;

}


.highlight-meta {

    margin-top: 5px;

    color: #7e898d;

    font-size: 7px;

}


.highlight-score {

    margin-top: 12px;

    font-size: 20px;

    font-weight: 700;

}


/* =========================================================
   PANELS
========================================================= */

.panel {

    margin-bottom: 20px;

    background: #ffffff;

    border:
        1px solid
        #deddd8;

}


.panel-header {

    padding:
        17px 20px;

    border-bottom:
        1px solid
        #e7e5e1;

}


.panel-title {

    font-size: 13px;

    font-weight: 600;

}


.panel-subtitle {

    margin-top: 4px;

    color: #899398;

    font-size: 7px;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrap {

    overflow-x: auto;

}


table {

    width: 100%;

    border-collapse: collapse;

}


th {

    padding:
        10px 9px;

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
        10px 9px;

    border-bottom:
        1px solid
        #eceae6;

    color: #455a64;

    font-size: 8px;

}


.score {

    font-weight: 700;

}


.bar-container {

    width: 130px;

}


.bar {

    height: 6px;

    background: #e5e6e3;

    border-radius: 4px;

    overflow: hidden;

}


.bar-fill {

    height: 100%;

    background: #607d8b;

}


.bar-text {

    margin-top: 4px;

    color: #899398;

    font-size: 6px;

}


.position {

    font-weight: 700;

    text-align: center;

}


/* =========================================================
   GRADE GRID
========================================================= */

.grade-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(75px, 1fr)
        );

    gap: 8px;

    padding: 18px;

}


.grade {

    padding: 12px;

    background: #f5f5f2;

    text-align: center;

}


.grade-letter {

    font-size: 17px;

    font-weight: 700;

}


.grade-count {

    margin-top: 4px;

    color: #68767b;

    font-size: 7px;

}


.grade-percent {

    margin-top: 3px;

    color: #929b9e;

    font-size: 6px;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        50px 20px;

    text-align: center;

    color: #899398;

    font-size: 9px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .filter-grid {

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


    .stats {

        grid-template-columns:
            1fr;

    }


    .highlights {

        grid-template-columns:
            1fr;

    }


    .filter-grid {

        grid-template-columns:
            1fr;

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
    Report Approval
</a>


<a
    href="mark_submissions.php"
    class="nav-link"
>
    Mark Submissions
</a>


<a
    href="analytics.php"
    class="nav-link active"
>
    Academic Analytics
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

    Academic Analytics

</div>


<div class="topbar-right">

    HIBS ADMINISTRATION

</div>


</header>


<main class="content">


<!-- =====================================================
     TITLE
====================================================== -->

<div class="page-title">

    <h1>
        Academic Performance Analytics
    </h1>

    <p>
        Analyse published student results across the school.
    </p>

</div>


<!-- =====================================================
     FILTER
====================================================== -->

<section class="filter">


<div class="filter-title">

    Report Filters

</div>


<form
    method="GET"
>


<div class="filter-grid">


<div class="field">

<label>
    Academic Year
</label>


<select
    name="academic_year_id"
    onchange="
        this.form.submit();
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
        (int)$classId
        ===
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


<div>

<button
    type="submit"
    class="filter-button"
>
    Apply
</button>

</div>


</div>


</form>


</section>


<div class="filter-summary">

    Showing:

    <strong>
        <?= h(
            $selectedYearName
        ) ?>
    </strong>

    &nbsp; • &nbsp;

    <strong>
        <?= h(
            $selectedTermName
        ) ?>
    </strong>

    &nbsp; • &nbsp;

    <strong>
        <?= h(
            $selectedClassName
        ) ?>
    </strong>

</div>


<!-- =====================================================
     STATISTICS
====================================================== -->

<div class="stats">


<div class="stat">

    <div class="stat-label">
        Students
    </div>

    <div class="stat-value">

        <?= number_format(
            $totalStudents
        ) ?>

    </div>

    <div class="stat-note">
        Published student reports
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        School Average
    </div>

    <div class="stat-value">

        <?= number_format(
            $schoolAverage,
            2
        ) ?>%

    </div>

    <div class="stat-note">
        Overall average
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Highest Average
    </div>

    <div class="stat-value">

        <?= number_format(
            $highestScore,
            2
        ) ?>%

    </div>

    <div class="stat-note">
        Highest student average
    </div>

</div>


<div class="stat">

    <div class="stat-label">
        Lowest Average
    </div>

    <div class="stat-value">

        <?= number_format(
            $lowestScore,
            2
        ) ?>%

    </div>

    <div class="stat-note">
        Lowest student average
    </div>

</div>


</div>


<!-- =====================================================
     TOP / LOWEST
====================================================== -->

<div class="highlights">


<div class="highlight">


<div class="highlight-title">

    Highest Performing Student

</div>


<?php if (
    $highestStudent
): ?>


<div class="highlight-name">

    <?= h(
        $highestStudent[
            "student_name"
        ]
    ) ?>

</div>


<div class="highlight-meta">

    <?= h(
        $highestStudent[
            "student_id"
        ]
    ) ?>

    &nbsp; • &nbsp;

    <?= h(
        $highestStudent[
            "class_name"
        ]
    ) ?>

</div>


<div class="highlight-score">

    <?= number_format(
        (float)$highestStudent[
            "average_score"
        ],
        2
    ) ?>%

</div>


<?php else: ?>


<div class="highlight-meta">

    No published results available.

</div>


<?php endif; ?>


</div>


<div class="highlight">


<div class="highlight-title">

    Lowest Performing Student

</div>


<?php if (
    $lowestStudent
): ?>


<div class="highlight-name">

    <?= h(
        $lowestStudent[
            "student_name"
        ]
    ) ?>

</div>


<div class="highlight-meta">

    <?= h(
        $lowestStudent[
            "student_id"
        ]
    ) ?>

    &nbsp; • &nbsp;

    <?= h(
        $lowestStudent[
            "class_name"
        ]
    ) ?>

</div>


<div class="highlight-score">

    <?= number_format(
        (float)$lowestStudent[
            "average_score"
        ],
        2
    ) ?>%

</div>


<?php else: ?>


<div class="highlight-meta">

    No published results available.

</div>


<?php endif; ?>


</div>


</div>


<!-- =====================================================
     CLASS PERFORMANCE
====================================================== -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Class Performance

    </div>

    <div class="panel-subtitle">

        Comparison of published class averages.

    </div>

</div>


<?php if (
    count($classPerformance)
): ?>


<div class="table-wrap">


<table>


<thead>

<tr>

<th>
    #
</th>

<th>
    Class
</th>

<th>
    Students
</th>

<th>
    Average
</th>

<th>
    Highest
</th>

<th>
    Lowest
</th>

<th>
    Performance
</th>

</tr>

</thead>


<tbody>


<?php

$classPosition = 1;

foreach (
    $classPerformance
    as $row
):

?>


<tr>


<td>

    <?= $classPosition++ ?>

</td>


<td>

    <strong>

        <?= h(
            $row[
                "class_name"
            ]
        ) ?>

    </strong>

</td>


<td>

    <?= h(
        $row[
            "students"
        ]
    ) ?>

</td>


<td class="score">

    <?= number_format(
        (float)$row[
            "average_score"
        ],
        2
    ) ?>%

</td>


<td>

    <?= number_format(
        (float)$row[
            "highest_score"
        ],
        2
    ) ?>%

</td>


<td>

    <?= number_format(
        (float)$row[
            "lowest_score"
        ],
        2
    ) ?>%

</td>


<td>


<div class="bar-container">


<div class="bar">

    <div
        class="bar-fill"
        style="
            width:
            <?= min(
                100,
                max(
                    0,
                    (float)$row[
                        "average_score"
                    ]
                )
            ) ?>%;
        "
    ></div>

</div>


<div class="bar-text">

    <?= number_format(
        (float)$row[
            "average_score"
        ],
        2
    ) ?>%

</div>


</div>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty">

    No published class results are available.

</div>


<?php endif; ?>


</section>


<!-- =====================================================
     SUBJECT PERFORMANCE
====================================================== -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Subject Performance

    </div>

    <div class="panel-subtitle">

        Average performance by subject.

    </div>

</div>


<?php if (
    count($subjectPerformance)
): ?>


<div class="table-wrap">


<table>


<thead>

<tr>

<th>
    #
</th>

<th>
    Subject
</th>

<th>
    Entries
</th>

<th>
    Average
</th>

<th>
    Highest
</th>

<th>
    Lowest
</th>

<th>
    Performance
</th>

</tr>

</thead>


<tbody>


<?php

$subjectPosition = 1;

foreach (
    $subjectPerformance
    as $row
):

?>


<tr>


<td>

    <?= $subjectPosition++ ?>

</td>


<td>

    <strong>

        <?= h(
            $row[
                "subject_name"
            ]
        ) ?>

    </strong>

</td>


<td>

    <?= h(
        $row[
            "entries"
        ]
    ) ?>

</td>


<td class="score">

    <?= number_format(
        (float)$row[
            "average_score"
        ],
        2
    ) ?>%

</td>


<td>

    <?= number_format(
        (float)$row[
            "highest_score"
        ],
        2
    ) ?>%

</td>


<td>

    <?= number_format(
        (float)$row[
            "lowest_score"
        ],
        2
    ) ?>%

</td>


<td>


<div class="bar-container">


<div class="bar">

    <div
        class="bar-fill"
        style="
            width:
            <?= min(
                100,
                max(
                    0,
                    (float)$row[
                        "average_score"
                    ]
                )
            ) ?>%;
        "
    ></div>

</div>


<div class="bar-text">

    <?= number_format(
        (float)$row[
            "average_score"
        ],
        2
    ) ?>%

</div>


</div>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty">

    No subject results are available.

</div>


<?php endif; ?>


</section>


<!-- =====================================================
     GRADE DISTRIBUTION
====================================================== -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Grade Distribution

    </div>

    <div class="panel-subtitle">

        Distribution of grades across published subject results.

    </div>

</div>


<?php if (
    count($grades)
): ?>


<?php

$totalGradeEntries =
    array_sum(
        array_column(
            $grades,
            "total"
        )
    );

?>


<div class="grade-grid">


<?php foreach (
    $grades
    as $grade
): ?>


<div class="grade">


<div class="grade-letter">

    <?= h(
        $grade[
            "grade"
        ]
    ) ?>

</div>


<div class="grade-count">

    <?= number_format(
        (int)$grade[
            "total"
        ]
    ) ?>

    entries

</div>


<div class="grade-percent">

<?=
    $totalGradeEntries > 0

        ? number_format(
            (
                (
                    (int)$grade[
                        "total"
                    ]
                    /
                    $totalGradeEntries
                )
                *
                100
            ),
            1
        )

        : "0"
?>%

</div>


</div>


<?php endforeach; ?>


</div>


<?php else: ?>


<div class="empty">

    No grades are available.

</div>


<?php endif; ?>


</section>


<!-- =====================================================
     STUDENT RANKING
====================================================== -->

<section class="panel">


<div class="panel-header">

    <div class="panel-title">

        Student Performance Ranking

    </div>

    <div class="panel-subtitle">

        Top 20 students based on published report averages.

    </div>

</div>


<?php if (
    count($rankings)
): ?>


<div class="table-wrap">


<table>


<thead>

<tr>

<th>
    Rank
</th>

<th>
    Student
</th>

<th>
    Student ID
</th>

<th>
    Class
</th>

<th>
    Average
</th>

<th>
    Report Position
</th>

</tr>

</thead>


<tbody>


<?php

$rank = 1;

foreach (
    $rankings
    as $row
):

?>


<tr>


<td class="position">

    <?= $rank++ ?>

</td>


<td>

    <strong>

        <?= h(
            $row[
                "student_name"
            ]
        ) ?>

    </strong>

</td>


<td>

    <?= h(
        $row[
            "student_id"
        ]
    ) ?>

</td>


<td>

    <?= h(
        $row[
            "class_name"
        ]
    ) ?>

</td>


<td class="score">

    <?= number_format(
        (float)$row[
            "average_score"
        ],
        2
    ) ?>%

</td>


<td class="position">

<?php if (
    $row[
        "position"
    ] !== null
): ?>

    <?= h(
        $row[
            "position"
        ]
    ) ?>

    <?php if (
        $row[
            "class_size"
        ]
    ): ?>

        /
        <?= h(
            $row[
                "class_size"
            ]
        ) ?>

    <?php endif; ?>

<?php else: ?>

    —

<?php endif; ?>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty">

    No published student rankings are available.

</div>


<?php endif; ?>


</section>


</main>


</div>


</body>

</html>
