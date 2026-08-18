<?php

function generateTeacherComment(
    float $average
): string {

    if ($average >= 90) {

        return
            "An outstanding performance. "
            . "The student has demonstrated excellent "
            . "understanding and consistently high achievement.";

    }

    if ($average >= 80) {

        return
            "Excellent performance. "
            . "The student has shown a very strong understanding "
            . "of the work covered and should maintain this standard.";

    }

    if ($average >= 70) {

        return
            "Very good performance. "
            . "The student has demonstrated good understanding "
            . "and is making commendable progress.";

    }

    if ($average >= 60) {

        return
            "A good performance. "
            . "The student is making satisfactory progress "
            . "and should continue to work consistently.";

    }

    if ($average >= 50) {

        return
            "The student has made satisfactory progress. "
            . "Greater consistency and additional effort "
            . "will help improve future performance.";

    }

    if ($average >= 40) {

        return
            "The student needs to improve academic performance. "
            . "More consistent effort, revision and support "
            . "are recommended.";

    }

    return
        "The student's current performance requires "
        . "significant improvement. "
        . "A structured programme of additional support "
        . "and regular revision is recommended.";
}
