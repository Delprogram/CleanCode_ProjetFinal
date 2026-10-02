<?php

declare(strict_types=1);

final class AnalyticsTrackingListener implements BookingConfirmationListenerInterface
{
    public function __construct(
        private readonly ?AnalyticsClient $analyticsClient = null
    ) {
    }

    private function getAnalyticsClient(): AnalyticsClient
    {
        return $this->analyticsClient ?? new AnalyticsClient();
    }

    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        $this->getAnalyticsClient()->track('booking_confirmed', [
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer->id,
            'total' => $total,
            'pass_type' => $booking->passType,
            'items_count' => count($booking->items),
        ]);
    }
}
