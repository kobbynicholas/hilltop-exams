<?php

function determinePromotionStatus(
    float $average,
    float $attendance
): string {

    if (
        $average >= 50 &&
        $attendance >= 75
    ) {

        return "Promoted";
    }


    if (
        $average >= 45 &&
        $attendance >= 70
    ) {

        return "Conditional";
    }


    return "Not Promoted";
}
