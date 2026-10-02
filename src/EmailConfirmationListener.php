<?php

declare(strict_types=1);

final class EmailConfirmationListener implements BookingConfirmationListenerInterface
{
    public function __construct(
        private readonly ?EmailService $emailService = null
    ) {
    }

    private function getEmailService(): EmailService
    {
        return $this->emailService ?? new EmailService();
    }

    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        $this->getEmailService()->sendConfirmation($booking->customer->email, $booking->id);
    }
}
