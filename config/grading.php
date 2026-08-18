<?php

/*
|--------------------------------------------------------------------------
| HIBS REPORTING SYSTEM
| GRADING ENGINE
|--------------------------------------------------------------------------
|
| This file contains the school's internal percentage
| grading rules.
|
| Change the values here if HIBS adopts a different
| internal grading structure.
|
*/


if (!function_exists("hibs_get_grade")) {

    function hibs_get_grade($score): string
    {
        if ($score === null || $score === "") {
            return "";
        }


        $score = (float)$score;


        /*
        |--------------------------------------------------------------------------
        | LIMIT SCORE
        |--------------------------------------------------------------------------
        */

        if ($score < 0) {
            $score = 0;
        }


        if ($score > 100) {
            $score = 100;
        }


        /*
        |--------------------------------------------------------------------------
        | GRADE
        |--------------------------------------------------------------------------
        */

        if ($score >= 90) {

            return "A*";

        } elseif ($score >= 80) {

            return "A";

        } elseif ($score >= 70) {

            return "B";

        } elseif ($score >= 60) {

            return "C";

        } elseif ($score >= 50) {

            return "D";

        } elseif ($score >= 40) {

            return "E";

        } elseif ($score >= 30) {

            return "F";

        }

        return "U";
    }
}


/*
|--------------------------------------------------------------------------
| GRADE DESCRIPTION
|--------------------------------------------------------------------------
*/

if (!function_exists("hibs_grade_description")) {

    function hibs_grade_description(
        string $grade
    ): string {

        return match ($grade) {

            "A*" =>
                "Outstanding",

            "A" =>
                "Excellent",

            "B" =>
                "Very Good",

            "C" =>
                "Good",

            "D" =>
                "Satisfactory",

            "E" =>
                "Pass",

            "F" =>
                "Below Expected Standard",

            "U" =>
                "Ungraded / Needs Improvement",

            default =>
                ""

        };
    }
}


/*
|--------------------------------------------------------------------------
| GRADE POINT
|--------------------------------------------------------------------------
*/

if (!function_exists("hibs_grade_point")) {

    function hibs_grade_point(
        string $grade
    ): float {

        return match ($grade) {

            "A*" => 4.0,
            "A"  => 4.0,
            "B"  => 3.0,
            "C"  => 2.0,
            "D"  => 1.5,
            "E"  => 1.0,
            "F"  => 0.5,
            "U"  => 0.0,

            default => 0.0

        };
    }
}


/*
|--------------------------------------------------------------------------
| GRADE COLOUR CLASS
|--------------------------------------------------------------------------
|
| Used by the report interface.
|
*/

if (!function_exists("hibs_grade_class")) {

    function hibs_grade_class(
        string $grade
    ): string {

        return match ($grade) {

            "A*" =>
                "grade-excellent",

            "A" =>
                "grade-excellent",

            "B" =>
                "grade-good",

            "C" =>
                "grade-good",

            "D" =>
                "grade-average",

            "E" =>
                "grade-pass",

            "F" =>
                "grade-warning",

            "U" =>
                "grade-fail",

            default =>
                ""

        };
    }
}
