<?php

declare(strict_types=1);

final class PricingService
{
    public function calculate(Booking $booking): float
    {
        $grossTotal = $this->calculateGrossTotal($booking);
        $discountRate = $this->getCustomerDiscountRate($booking->customer->type, $grossTotal);

        $totalAfterCustomerDiscount = $grossTotal * (1.0 - $discountRate);

        if ($booking->passType === '3days') {
            $totalAfterCustomerDiscount -= 20.0;
        }

        return round(max(0.0, $totalAfterCustomerDiscount), 2);
    }

    public function calculateGrossTotal(Booking $booking): float
    {
        $total = 0.0;

        foreach ($booking->items as $item) {
            if ($item->quantity <= 0) {
                throw new RuntimeException('Invalid quantity');
            }
            $total += $item->ticket->price * $item->quantity;
        }

        return $total;
    }

    public function getCustomerDiscountRate(string $customerType, float $grossTotal): float
    {
        if ($customerType !== 'vip') {
            return 0.0;
        }

        if ($grossTotal < 100.0) {
            return 0.05;
        }

        if ($grossTotal < 300.0) {
            return 0.10;
        }

        return 0.15;
    }
}
