<?php

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| APPROVE REPORT
|--------------------------------------------------------------------------
| Changes:
|
| Draft -> Approved
|
| Only administrators can perform this action.
| The action MUST be submitted using POST.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| ERROR REPORTING
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
| ADMIN ACCESS
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
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    http_response_code(405);

    exit(
        "Method Not Allowed"
    );
}


/*
|--------------------------------------------------------------------------
| CSRF PROTECTION
|--------------------------------------------------------------------------
*/

$sessionToken =
    $_SESSION["csrf_token"]
    ?? "";

$postedToken =
    $_POST["csrf_token"]
    ?? "";


if (
    empty($sessionToken) ||
    empty($postedToken) ||
    !hash_equals(
        $sessionToken,
        $postedToken
    )
) {

    $_SESSION["report_message"] =
        "Security verification failed. Please try again.";

    $_SESSION["report_message_type"] =
        "error";

    header(
        "Location: report_cards.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$student_id = filter_input(
    INPUT_POST,
    "student_id",
    FILTER_VALIDATE_INT
);

$class_id = filter_input(
    INPUT_POST,
    "class_id",
    FILTER_VALIDATE_INT
);

$term_id = filter_input(
    INPUT_POST,
    "term_id",
    FILTER_VALIDATE_INT
);


if (
    !$student_id ||
    !$class_id ||
    !$term_id
) {

    $_SESSION["report_message"] =
        "Invalid report information.";

    $_SESSION["report_message_type"] =
        "error";

    header(
        "Location: report_cards.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | VERIFY STUDENT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            student_id,
            first_name,
            middle_name,
            last_name,
            class_id

        FROM students

        WHERE
            id = ?
            AND class_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $student_id,
        $class_id
    ]);

    $student =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$student) {

        throw new Exception(
            "Student was not found in the selected class."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY TERM
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            term_name,
            academic_year_id

        FROM terms

        WHERE id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $term_id
    ]);

    $term =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$term) {

        throw new Exception(
            "Selected academic term was not found."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY RESULTS
    |--------------------------------------------------------------------------
    |
    | A report should not be approved when academic
    | results do not exist.
    |
    */

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS result_count

        FROM student_results

        WHERE
            student_id = ?
            AND class_id = ?
            AND term_id = ?
    ");

    $stmt->execute([
        $student_id,
        $class_id,
        $term_id
    ]);

    $resultCheck =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        (int)(
            $resultCheck["result_count"]
            ?? 0
        ) <= 0
    ) {

        throw new Exception(
            "The student's academic results have not been generated yet."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD REPORT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            student_id,
            class_id,
            term_id,
            report_status

        FROM report_card_records

        WHERE
            student_id = ?
            AND class_id = ?
            AND term_id = ?

        LIMIT 1

        FOR UPDATE
    ");

    $stmt->execute([
        $student_id,
        $class_id,
        $term_id
    ]);

    $report =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | REPORT MUST EXIST
    |--------------------------------------------------------------------------
    */

    if (!$report) {

        throw new Exception(
            "The report details have not been created for this student."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS VALIDATION
    |--------------------------------------------------------------------------
    */

    $currentStatus =
        $report["report_status"]
        ?? "Draft";


    if (
        $currentStatus === "Published"
    ) {

        throw new Exception(
            "This report has already been published."
        );
    }


    if (
        $currentStatus === "Approved"
    ) {

        throw new Exception(
            "This report has already been approved."
        );
    }


    if (
        $currentStatus !== "Draft"
    ) {

        throw new Exception(
            "This report cannot be approved from its current status."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY REPORT DETAILS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT

            days_opened,
            days_present,
            days_absent,
            conduct,
            promotion_status

        FROM report_card_records

        WHERE id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $report["id"]
    ]);

    $details =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$details) {

        throw new Exception(
            "Report details could not be verified."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REQUIRED REPORT INFORMATION
    |--------------------------------------------------------------------------
    */

    if (
        $details["days_opened"] === null
        ||
        $details["days_present"] === null
        ||
        $details["days_absent"] === null
    ) {

        throw new Exception(
            "Attendance information is incomplete."
        );
    }


    if (
        trim(
            (string)(
                $details["conduct"]
                ?? ""
            )
        ) === ""
    ) {

        throw new Exception(
            "Conduct information is incomplete."
        );
    }


    if (
        trim(
            (string)(
                $details["promotion_status"]
                ?? ""
            )
        ) === ""
    ) {

        throw new Exception(
            "Promotion status is incomplete."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | APPROVE REPORT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE report_card_records

        SET

            report_status = 'Approved',

            approved_at = NOW(),

            approved_by = ?

        WHERE

            id = ?

            AND student_id = ?

            AND class_id = ?

            AND term_id = ?

            AND report_status = 'Draft'
    ");

    $stmt->execute([

        $_SESSION["user_id"],

        $report["id"],

        $student_id,

        $class_id,

        $term_id

    ]);


    /*
    |--------------------------------------------------------------------------
    | VERIFY UPDATE
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            report_status

        FROM report_card_records

        WHERE id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $report["id"]
    ]);

    $updated =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$updated ||
        $updated["report_status"] !== "Approved"
    ) {

        throw new Exception(
            "The report could not be approved."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    $_SESSION["report_message"] =
        "Report approved successfully.";

    $_SESSION["report_message_type"] =
        "success";


    header(
        "Location: report_cards.php?class_id="
        . urlencode(
            (string)$class_id
        )
        . "&term_id="
        . urlencode(
            (string)$term_id
        )
        . "&approved=1"
    );

    exit;


} catch (Throwable $e) {


    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    /*
    |--------------------------------------------------------------------------
    | USER-FRIENDLY ERROR
    |--------------------------------------------------------------------------
    */

    $_SESSION["report_message"] =
        $e->getMessage();

    $_SESSION["report_message_type"] =
        "error";


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */

    header(
        "Location: report_cards.php?class_id="
        . urlencode(
            (string)$class_id
        )
        . "&term_id="
        . urlencode(
            (string)$term_id
        )
    );

    exit;
}
