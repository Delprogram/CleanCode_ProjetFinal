<?php

declare(strict_types=1);

interface BookingConfirmationListenerInterface
{
    public function onBookingConfirmed(Booking $booking, float $total): void;
}
