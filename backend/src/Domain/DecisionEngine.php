<?php

declare(strict_types=1);

namespace CarMoneyLab\Domain;

/**
 * Решение по заявке на основании LTV и пробега.
 *
 *   LTV < approve_max               -> approve
 *   approve_max <= LTV <= review_max -> review
 *   LTV > review_max                -> reject
 *
 * При пробеге выше reviewMaxMileageKm решение `approve` по LTV переводится
 * в `review`; решения `review` и `reject` по LTV сохраняются без изменений
 * (REQ-MILEAGE-02/03).
 */
final class DecisionEngine
{
    public const APPROVE = 'approve';
    public const REVIEW = 'review';
    public const REJECT = 'reject';

    private float $approveMax;
    private float $reviewMax;
    private int $reviewMaxMileageKm;

    /** @param array{approve_max:float,review_max:float,review_max_mileage_km:int} $thresholds */
    public function __construct(array $thresholds)
    {
        $this->approveMax = $thresholds['approve_max'];
        $this->reviewMax = $thresholds['review_max'];
        $this->reviewMaxMileageKm = $thresholds['review_max_mileage_km'];
    }

    public function decide(float $ltv, int $mileage): string
    {
        if ($ltv < $this->approveMax) {
            $decision = self::APPROVE;
        } elseif ($ltv <= $this->reviewMax) {
            $decision = self::REVIEW;
        } else {
            $decision = self::REJECT;
        }

        if ($decision === self::APPROVE && $mileage > $this->reviewMaxMileageKm) {
            return self::REVIEW;
        }

        return $decision;
    }
}
