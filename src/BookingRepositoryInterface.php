<?php

declare(strict_types=1);

interface BookingRepositoryInterface
{
    public function save(Booking $booking, float $total): void;
}
