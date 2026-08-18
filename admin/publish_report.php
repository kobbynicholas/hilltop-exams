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


$stmt = $conn->prepare("
    UPDATE report_card_records
    SET
        report_status = 'Published',
        published_at = NOW()
    WHERE
        student_id = ?
        AND class_id = ?
        AND term_id = ?
        AND report_status = 'Approved'
");

$stmt->execute([
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
    . "&published=1"
);

exit;
