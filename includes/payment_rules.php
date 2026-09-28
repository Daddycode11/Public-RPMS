<?php

/**
 * Calculate the calendar-day payment adjustment for a base amount.
 *
 * Days 1-5 receive a 5% discount, days 6-20 are regular, and days 21
 * through the actual last day of the month receive a 20% penalty.
 */
function calculatePaymentAdjustment(float $baseAmount, ?DateTimeInterface $paymentDate = null): array
{
    $baseAmount = round(max(0, $baseAmount), 2);
    $paymentDate = $paymentDate ?? new DateTimeImmutable('today');
    $day = (int)$paymentDate->format('j');

    $discount = 0.0;
    $penalty = 0.0;
    $rule = 'regular';

    if ($day <= 5) {
        $discount = round($baseAmount * 0.05, 2);
        $rule = 'discount';
    } elseif ($day >= 21) {
        $penalty = round($baseAmount * 0.20, 2);
        $rule = 'penalty';
    }

    return [
        'base_amount' => $baseAmount,
        'discount' => $discount,
        'penalty' => $penalty,
        'total' => round($baseAmount - $discount + $penalty, 2),
        'rule' => $rule,
        'payment_day' => $day,
        'month_last_day' => (int)$paymentDate->format('t'),
    ];
}
