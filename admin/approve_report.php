<?php

session_start();

require_once "../config/db.php";

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$student_id =
    (int)($_GET["student_id"] ?? 0);

$class_id =
    (int)($_GET["class_id"] ?? 0);

$term_id =
    (int)($_GET["term_id"] ?? 0);


if (
    !$student_id ||
    !$class_id ||
    !$term_id
) {

    die("Invalid report.");
}


$stmt = $conn->prepare("
    UPDATE report_card_records
    SET
        report_status = 'Approved',
        approved_by = ?,
        approved_at = NOW()
    WHERE
        student_id = ?
        AND class_id = ?
        AND term_id = ?
");

$stmt->execute([
    $_SESSION["user_id"],
    $student_id,
    $class_id,
    $term_id
]);


header(
    "Location: report_cards.php"
    . "?class_id="
    . $class_id
    . "&term_id="
    . $term_id
    . "&approved=1"
);

exit;
