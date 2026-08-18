<?php

session_start();

require_once "../config/db.php";

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
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| REPORT ID
|--------------------------------------------------------------------------
*/

$reportId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);


if (!$reportId) {

    header("Location: reports.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD REPORT
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        SELECT

            r.id,
            r.report_status,

            s.first_name,
            s.middle_name,
            s.last_name,

            s.student_id AS student_number,

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

    $report = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$report) {

        header("Location: reports.php");
        exit;
    }


} catch (Throwable $e) {

    die(
        "Unable to load the report."
    );
}


/*
|--------------------------------------------------------------------------
| STUDENT NAME
|--------------------------------------------------------------------------
*/

$studentName = trim(
    implode(
        " ",
        array_filter([
            $report["first_name"] ?? "",
            $report["middle_name"] ?? "",
            $report["last_name"] ?? ""
        ])
    )
);


/*
|--------------------------------------------------------------------------
| PUBLISH
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {


    /*
    |--------------------------------------------------------------------------
    | ONLY APPROVED REPORTS CAN BE PUBLISHED
    |--------------------------------------------------------------------------
    */

    if (
        $report["report_status"] !== "Approved"
    ) {

        $error =
            "This report cannot be published because its current status is "
            . $report["report_status"]
            . ".";

    } else {

        try {

            $conn->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | PUBLISH
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                UPDATE report_card_records

                SET

                    report_status = 'Published',

                    published_at = NOW(),

                    updated_at = NOW()

                WHERE

                    id = ?

                    AND report_status = 'Approved'
            ");


            $stmt->execute([
                $reportId
            ]);


            /*
            |--------------------------------------------------------------------------
            | VERIFY
            |--------------------------------------------------------------------------
            */

            if (
                $stmt->rowCount() !== 1
            ) {

                throw new Exception(
                    "The report could not be published."
                );
            }


            $conn->commit();


            /*
            |--------------------------------------------------------------------------
            | RETURN
            |--------------------------------------------------------------------------
            */

            header(
                "Location: reports.php?published=1"
            );

            exit;


        } catch (Throwable $e) {

            if (
                $conn->inTransaction()
            ) {

                $conn->rollBack();
            }


            $error =
                "Publication failed. "
                . $e->getMessage();
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
    HIBS Reports | Publish Report
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


.container {

    width: 100%;

    max-width: 720px;

    margin: 70px auto;

    padding: 20px;
}


.card {

    background: #ffffff;

    border:
        1px solid
        #deddd8;
}


.header {

    padding: 25px;

    border-bottom:
        1px solid
        #e7e5e1;
}


.brand {

    color: #263238;

    font-size: 17px;

    font-weight: 700;

    letter-spacing: 1px;
}


.subtitle {

    margin-top: 6px;

    color: #899398;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: 1px;
}


.body {

    padding: 25px;
}


.title {

    margin: 0;

    color: #37474f;

    font-size: 22px;

    font-weight: 600;
}


.description {

    margin-top: 8px;

    color: #7a858a;

    font-size: 10px;

    line-height: 1.7;
}


.student {

    margin-top: 22px;

    padding: 18px;

    background: #f7f7f4;

    border:
        1px solid
        #e5e4df;
}


.student-name {

    color: #37474f;

    font-size: 16px;

    font-weight: 600;
}


.student-details {

    margin-top: 8px;

    color: #7a858a;

    font-size: 9px;

    line-height: 1.8;
}


.status {

    display: inline-block;

    margin-top: 14px;

    padding:
        7px 10px;

    background: #e9eef2;

    color: #506675;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;
}


.warning {

    margin-top: 20px;

    padding: 15px;

    background: #eef2f3;

    border-left:
        3px solid
        #607d8b;

    color: #586a71;

    font-size: 9px;

    line-height: 1.7;
}


.error {

    margin-bottom: 18px;

    padding: 13px;

    background: #fbf1f1;

    border:
        1px solid
        #e1c8c8;

    color: #8b4b4b;

    font-size: 9px;
}


.actions {

    margin-top: 25px;

    display: flex;

    justify-content: flex-end;

    gap: 8px;
}


.btn {

    display: inline-block;

    padding:
        11px 15px;

    border: 0;

    border-radius: 3px;

    font-family: inherit;

    font-size: 9px;

    font-weight: bold;

    text-decoration: none;

    cursor: pointer;
}


.cancel {

    background: #ffffff;

    color: #68757a;

    border:
        1px solid
        #d4d3ce;
}


.publish {

    background: #657d70;

    color: #ffffff;
}


.publish:hover {

    background: #50665a;
}


@media(max-width:600px) {

    .container {

        margin: 25px auto;

        padding: 12px;
    }

    .actions {

        flex-direction: column;
    }

    .btn {

        width: 100%;

        text-align: center;
    }

}

</style>

</head>


<body>


<div class="container">


    <div class="card">


        <div class="header">

            <div class="brand">
                HIBS REPORTS
            </div>

            <div class="subtitle">
                Hilltop International British School
            </div>

        </div>


        <div class="body">


            <?php if (
                isset($error)
            ): ?>

                <div class="error">

                    <?= h($error) ?>

                </div>

            <?php endif; ?>


            <h1 class="title">
                Publish Academic Report
            </h1>


            <div class="description">

                Publishing this report makes it an official
                HIBS academic record and allows the student
                to access it through the student portal.

            </div>


            <div class="student">


                <div class="student-name">

                    <?= h(
                        $studentName
                    ) ?>

                </div>


                <div class="student-details">

                    Student ID:
                    <strong>
                        <?= h(
                            $report["student_number"]
                        ) ?>
                    </strong>

                    <br>

                    Class:
                    <strong>
                        <?= h(
                            $report["class_name"]
                        ) ?>
                    </strong>

                    <br>

                    Academic Year:
                    <strong>
                        <?= h(
                            $report["academic_year"]
                        ) ?>
                    </strong>

                    <br>

                    Term:
                    <strong>
                        <?= h(
                            $report["term_name"]
                        ) ?>
                    </strong>

                </div>


                <span class="status">

                    <?= h(
                        $report["report_status"]
                    ) ?>

                </span>


            </div>


            <?php if (
                $report["report_status"] === "Approved"
            ): ?>


                <div class="warning">

                    This report has already passed the
                    approval stage.

                    <br><br>

                    Once published, the report will become
                    an official student record and will be
                    available through the student portal.

                    <br><br>

                    <strong>
                        Please verify the report one final
                        time before publishing.
                    </strong>

                </div>


                <form
                    method="POST"
                    class="actions"
                >

                    <a
                        href="reports.php"
                        class="btn cancel"
                    >
                        Cancel
                    </a>


                    <button
                        type="submit"
                        class="btn publish"
                        onclick="
                            return confirm(
                                'Publish this official academic report?'
                            );
                        "
                    >
                        Publish Report
                    </button>

                </form>


            <?php else: ?>


                <div class="warning">

                    This report is currently
                    <strong>
                        <?= h(
                            $report["report_status"]
                        ) ?>
                    </strong>.

                    Only reports with an
                    <strong>
                        Approved
                    </strong>
                    status can be published.

                </div>


                <div class="actions">

                    <a
                        href="reports.php"
                        class="btn cancel"
                    >
                        Return to Reports
                    </a>

                </div>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>
