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

/*
|--------------------------------------------------------------------------
| GET PARAMETERS
|--------------------------------------------------------------------------
*/

$class_id = (int)($_GET["class_id"] ?? 0);
$term_id = (int)($_GET["term_id"] ?? 0);

if ($class_id <= 0 || $term_id <= 0) {

    die("Invalid class or term.");
}


/*
|--------------------------------------------------------------------------
| VERIFY CLASS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id, class_name
    FROM classes
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$class_id]);

$class = $stmt->fetch();

if (!$class) {
    die("Class not found.");
}


/*
|--------------------------------------------------------------------------
| VERIFY TERM
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        t.id,
        t.term_name,
        ay.academic_year
    FROM terms t

    INNER JOIN academic_years ay
        ON ay.id = t.academic_year_id

    WHERE t.id = ?

    LIMIT 1
");

$stmt->execute([$term_id]);

$term = $stmt->fetch();

if (!$term) {
    die("Term not found.");
}


try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | GET STUDENTS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT id
        FROM students
        WHERE
            class_id = ?
            AND status = 'Active'
    ");

    $stmt->execute([$class_id]);

    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);


    /*
    |--------------------------------------------------------------------------
    | GET SUBJECTS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT DISTINCT subject_id
        FROM class_subjects
        WHERE class_id = ?
    ");

    $stmt->execute([$class_id]);

    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);


    /*
    |--------------------------------------------------------------------------
    | CALCULATE SUBJECT RESULTS
    |--------------------------------------------------------------------------
    */

    foreach ($students as $student_id) {

        foreach ($subjects as $subject_id) {


            /*
            |--------------------------------------------------------------
            | GET ASSESSMENT COMPONENTS
            |--------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT
                    ac.id,
                    ac.max_score,
                    ac.weight
                FROM subject_assessments sa

                INNER JOIN assessment_components ac
                    ON ac.id = sa.component_id

                WHERE
                    sa.class_id = ?
                    AND sa.subject_id = ?
                    AND ac.status = 'Active'
            ");

            $stmt->execute([
                $class_id,
                $subject_id
            ]);

            $components = $stmt->fetchAll();


            if (!$components) {
                continue;
            }


            /*
            |--------------------------------------------------------------
            | CALCULATE WEIGHTED TOTAL
            |--------------------------------------------------------------
            */

            $total = 0;


            foreach ($components as $component) {

                $stmt = $conn->prepare("
                    SELECT score
                    FROM mark_entries
                    WHERE
                        student_id = ?
                        AND class_id = ?
                        AND subject_id = ?
                        AND term_id = ?
                        AND component_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $student_id,
                    $class_id,
                    $subject_id,
                    $term_id,
                    $component["id"]
                ]);

                $mark = $stmt->fetchColumn();


                if ($mark !== false && $mark !== null) {

                    $weighted =
                        ((float)$mark /
                         (float)$component["max_score"])
                        *
                        (float)$component["weight"];

                    $total += $weighted;
                }
            }


            /*
            |--------------------------------------------------------------
            | FIND GRADE
            |--------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT
                    grade,
                    description,
                    remark
                FROM grade_scales
                WHERE
                    ? BETWEEN min_score AND max_score
                ORDER BY min_score DESC
                LIMIT 1
            ");

            $stmt->execute([$total]);

            $grade = $stmt->fetch();


            $grade_name = $grade["grade"] ?? null;
            $description = $grade["description"] ?? null;
            $remark = $grade["remark"] ?? null;


            /*
            |--------------------------------------------------------------
            | SAVE SUBJECT RESULT
            |--------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                INSERT INTO subject_results
                (
                    student_id,
                    class_id,
                    subject_id,
                    term_id,
                    total_score,
                    grade,
                    grade_description,
                    remark
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)

                ON DUPLICATE KEY UPDATE

                    total_score = VALUES(total_score),
                    grade = VALUES(grade),
                    grade_description =
                        VALUES(grade_description),
                    remark = VALUES(remark)
            ");

            $stmt->execute([
                $student_id,
                $class_id,
                $subject_id,
                $term_id,
                round($total, 2),
                $grade_name,
                $description,
                $remark
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE OVERALL STUDENT RESULTS
    |--------------------------------------------------------------------------
    */

    foreach ($students as $student_id) {

        $stmt = $conn->prepare("
            SELECT
                SUM(total_score) AS total_score,
                AVG(total_score) AS average_score
            FROM subject_results
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

        $result = $stmt->fetch();


        $total_score =
            (float)($result["total_score"] ?? 0);

        $average_score =
            (float)($result["average_score"] ?? 0);


        /*
        |--------------------------------------------------------------
        | SAVE STUDENT RESULT
        |--------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            INSERT INTO student_results
            (
                student_id,
                class_id,
                term_id,
                total_score,
                average_score
            )
            VALUES (?, ?, ?, ?, ?)

            ON DUPLICATE KEY UPDATE

                total_score = VALUES(total_score),
                average_score = VALUES(average_score)
        ");

        $stmt->execute([
            $student_id,
            $class_id,
            $term_id,
            round($total_score, 2),
            round($average_score, 2)
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE POSITION
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            student_id,
            average_score
        FROM student_results
        WHERE
            class_id = ?
            AND term_id = ?
        ORDER BY
            average_score DESC
    ");

    $stmt->execute([
        $class_id,
        $term_id
    ]);

    $results = $stmt->fetchAll();


    $position = 0;
    $lastScore = null;
    $actualPosition = 0;

    $classSize = count($results);


    foreach ($results as $result) {

        $actualPosition++;

        $score = (float)$result["average_score"];


        if (
            $lastScore === null ||
            $score < $lastScore
        ) {

            $position = $actualPosition;
        }


        $stmt = $conn->prepare("
            UPDATE student_results
            SET
                position = ?,
                class_size = ?
            WHERE
                student_id = ?
                AND class_id = ?
                AND term_id = ?
        ");

        $stmt->execute([
            $position,
            $classSize,
            $result["student_id"],
            $class_id,
            $term_id
        ]);


        $lastScore = $score;
    }


    $conn->commit();


    header(
        "Location: results.php"
        . "?class_id="
        . $class_id
        . "&term_id="
        . $term_id
        . "&calculated=1"
    );

    exit;


} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    die(
        "Result calculation failed: "
        . htmlspecialchars($e->getMessage())
    );
}
