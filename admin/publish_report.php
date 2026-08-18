<?php

session_start();

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| HIBS REPORTS
| PUBLISH REPORT
|--------------------------------------------------------------------------
| Changes:
|
| Approved -> Published
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
| TRANSACTION
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
    | LOAD REPORT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT

            id,
            student_id,
            class_id,
            term_id,

            report_status,

            approved_at,
            approved_by,

            published_at,
            published_by

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
            "The report does not exist."
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
        $currentStatus !== "Approved"
    ) {

        throw new Exception(
            "Only an approved report can be published."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY APPROVAL
    |--------------------------------------------------------------------------
    */

    if (
        empty(
            $report["approved_at"]
        )
    ) {

        throw new Exception(
            "The report does not have a valid approval record."
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
            promotion_status,

            teacher_comment,
            headteacher_comment

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
    | ATTENDANCE CHECK
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


    /*
    |--------------------------------------------------------------------------
    | CONDUCT CHECK
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | PROMOTION CHECK
    |--------------------------------------------------------------------------
    */

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
    | RESULTS CHECK
    |--------------------------------------------------------------------------
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
            "Academic results are missing. The report cannot be published."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PUBLISH REPORT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE report_card_records

        SET

            report_status = 'Published',

            published_at = NOW(),

            published_by = ?

        WHERE

            id = ?

            AND student_id = ?

            AND class_id = ?

            AND term_id = ?

            AND report_status = 'Approved'
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
    | VERIFY PUBLICATION
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT

            report_status,
            published_at,
            published_by

        FROM report_card_records

        WHERE id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $report["id"]
    ]);

    $published =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$published ||
        $published["report_status"] !== "Published"
    ) {

        throw new Exception(
            "The report could not be published."
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
        "Report published successfully.";

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
        . "&published=1"
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
    | ERROR MESSAGE
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
