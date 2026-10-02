<?php

declare(strict_types=1);

final class SqlSimulationBookingRepository implements BookingRepositoryInterface
{
    public function save(Booking $booking, float $total): void
    {
        echo "SQL INSERT booking={$booking->id} total={$total} status={$booking->status}" . PHP_EOL;
    }
}
