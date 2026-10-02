<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestRunner.php';

$tests = new TestRunner();

// 1. Test du repository de simulation SQL par défaut
ob_start();
$repo = new SqlSimulationBookingRepository();
$customer = new Customer(1, 'client@example.com');
$booking = new Booking(77, $customer);
$repo->save($booking, 100.0);
$out = ob_get_clean();

$tests->true(str_contains($out, 'SQL INSERT booking=77 total=100'), 'SqlSimulationBookingRepository logs SQL insert');

// 2. Test d'un faux repository en mémoire (Mock) démontrant le découplage et la testabilité
class InMemoryBookingRepository implements BookingRepositoryInterface
{
    /** @var array<int, float> */
    public array $saved = [];

    public function save(Booking $booking, float $total): void
    {
        $this->saved[$booking->id] = $total;
    }
}

$mockRepo = new InMemoryBookingRepository();
$service = new BookingService(
    bookingRepository: $mockRepo,
    confirmationListeners: [] // Supprime tous les effets de bord console pour tester isolément
);

$bookingTest = new Booking(88, $customer);
$bookingTest->addItem(new BookingItem(new Ticket('T1', 'Pass', 50.0), 1));

ob_start();
$service->confirm($bookingTest, 'stripe');
ob_end_clean();

$tests->true(isset($mockRepo->saved[88]), 'BookingService persists through BookingRepositoryInterface');
$tests->same(50.0, $mockRepo->saved[88], 'BookingService persists exact total in repository');

$tests->summary();
