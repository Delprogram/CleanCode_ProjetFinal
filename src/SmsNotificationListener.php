<?php

declare(strict_types=1);

final class SmsNotificationListener implements BookingConfirmationListenerInterface
{
    public function __construct(
        private readonly ?SmsClient $smsClient = null
    ) {
    }

    private function getSmsClient(): SmsClient
    {
        return $this->smsClient ?? new SmsClient();
    }

    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        $phone = $booking->customer->phone;
        if ($phone === null || trim($phone) === '') {
            return;
        }

        $this->getSmsClient()->send(
            $phone,
            "Réservation #{$booking->id} confirmée (total: {$total} €)."
        );
    }
}
