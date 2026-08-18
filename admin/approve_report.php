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
| REPORT NAME
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
| APPROVAL
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    /*
    |--------------------------------------------------------------------------
    | ONLY DRAFT CAN BE APPROVED
    |--------------------------------------------------------------------------
    */

    if (
        $report["report_status"] !== "Draft"
    ) {

        $error =
            "This report cannot be approved because its current status is "
            . $report["report_status"]
            . ".";

    } else {

        try {

            $conn->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | UPDATE STATUS
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                UPDATE report_card_records

                SET

                    report_status = 'Approved',

                    updated_at = NOW()

                WHERE

                    id = ?

                    AND report_status = 'Draft'
            ");

            $stmt->execute([
                $reportId
            ]);


            /*
            |--------------------------------------------------------------------------
            | VERIFY UPDATE
            |--------------------------------------------------------------------------
            */

            if (
                $stmt->rowCount() !== 1
            ) {

                throw new Exception(
                    "The report could not be approved."
                );
            }


            $conn->commit();


            /*
            |--------------------------------------------------------------------------
            | RETURN TO REPORT REGISTER
            |--------------------------------------------------------------------------
            */

            header(
                "Location: reports.php?approved=1"
            );

            exit;


        } catch (Throwable $e) {

            if (
                $conn->inTransaction()
            ) {

                $conn->rollBack();
            }


            $error =
                "Approval failed. "
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
    HIBS Reports | Approve Report
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

    background: #f3eee5;

    color: #806744;

    font-size: 8px;

    font-weight: bold;

    text-transform: uppercase;
}


.warning {

    margin-top: 20px;

    padding: 15px;

    background: #faf7ee;

    border-left:
        3px solid
        #a58a55;

    color: #766443;

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


.approve {

    background: #657d70;

    color: #ffffff;
}


.approve:hover {

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

        text-align: center;

        width: 100%;
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
                Approve Academic Report
            </h1>


            <div class="description">

                You are about to approve this academic
                report. Once approved, it will become
                available for the publication stage.

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
                $report["report_status"] === "Draft"
            ): ?>


                <div class="warning">

                    Please make sure that the student's
                    subject results, attendance, conduct,
                    comments, position and promotion status
                    have been reviewed before approval.

                    <br><br>

                    <strong>
                        Approval does not publish the report.
                    </strong>

                    The report must still be explicitly
                    published before the student can access
                    the official report.

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
                        class="btn approve"
                        onclick="
                            return confirm(
                                'Approve this report?'
                            );
                        "
                    >
                        Approve Report
                    </button>

                </form>


            <?php else: ?>


                <div class="warning">

                    This report is currently
                    <strong>
                        <?= h(
                            $report["report_status"]
                        ) ?>
                    </strong>
                    and cannot be approved again.

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
