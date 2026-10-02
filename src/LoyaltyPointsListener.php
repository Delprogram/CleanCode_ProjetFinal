<?php

declare(strict_types=1);

final class LoyaltyPointsListener implements BookingConfirmationListenerInterface
{
    public function __construct(
        private readonly ?LoyaltyService $loyaltyService = null
    ) {
    }

    private function getLoyaltyService(): LoyaltyService
    {
        return $this->loyaltyService ?? new LoyaltyService();
    }

    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        $points = (int) floor($total);
        if ($points > 0) {
            $this->getLoyaltyService()->addPoints($booking->customer->id, $points);
        }
    }
}
