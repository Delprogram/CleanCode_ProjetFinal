<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestRunner.php';

$tests = new TestRunner();

function createBooking(
    string $customerType = 'standard',
    string $passType = 'day',
    float $price = 50.0,
    int $quantity = 1,
    ?string $phone = '0600000000'
): Booking {
    $customer = new Customer(1, 'test@example.com', $phone, $customerType);
    $ticket = new Ticket('TEST', 'Ticket test', $price);
    $booking = new Booking(1, $customer, $passType);
    $booking->addItem(new BookingItem($ticket, $quantity));
    return $booking;
}

ob_start();
$service = new BookingService();

$standard = createBooking('standard', 'day', 50.0, 2);
$standardTotal = $service->confirm($standard, 'stripe');
$tests->near(100.0, $standardTotal, 'standard customer keeps initial total');
$tests->same('confirmed', $standard->status, 'booking becomes confirmed');

$vip = createBooking('vip', 'day', 50.0, 2);
$vipTotal = $service->confirm($vip, 'stripe');
$tests->near(90.0, $vipTotal, 'legacy VIP rule gives 10 percent discount');

$threeDays = createBooking('standard', '3days', 60.0, 2);
$threeDaysTotal = $service->confirm($threeDays, 'stripe');
$tests->near(100.0, $threeDaysTotal, 'new policy three day pass discount is 20 euros');

// Règle combinée VIP + 3days : remise 10% puis déduction de 20 euros
$vipThreeDays = createBooking('vip', '3days', 100.0, 1);
$vipThreeDaysTotal = $service->confirm($vipThreeDays, 'stripe');
$tests->near(70.0, $vipThreeDaysTotal, 'new policy VIP + 3 days applies 10% then minus 20 euros');

// Sécurisation des cas d'erreur métier
$tests->throws(
    function () use ($service) {
        $empty = new Booking(2, new Customer(2, 'empty@example.com'));
        $service->confirm($empty, 'stripe');
    },
    RuntimeException::class,
    'Empty booking',
    'empty booking throws RuntimeException'
);

$tests->throws(
    function () use ($service) {
        $badEmail = new Booking(3, new Customer(3, 'not-an-email'));
        $badEmail->addItem(new BookingItem(new Ticket('T1', 'Test', 10.0), 1));
        $service->confirm($badEmail, 'stripe');
    },
    RuntimeException::class,
    'Invalid email',
    'invalid customer email throws RuntimeException'
);

$tests->throws(
    function () use ($service) {
        $badQty = new Booking(4, new Customer(4, 'valid@example.com'));
        $badQty->addItem(new BookingItem(new Ticket('T1', 'Test', 10.0), 0));
        $service->confirm($badQty, 'stripe');
    },
    RuntimeException::class,
    'Invalid quantity',
    'zero or negative quantity throws RuntimeException'
);

$tests->throws(
    function () use ($service) {
        $booking = createBooking();
        $service->confirm($booking, 'payfast');
    },
    RuntimeException::class,
    'PayFast not implemented',
    'payfast currently throws not implemented'
);

$tests->throws(
    function () use ($service) {
        $booking = createBooking();
        $service->confirm($booking, 'unknown_method');
    },
    RuntimeException::class,
    'Unknown payment method',
    'unknown payment method throws RuntimeException'
);

ob_end_clean();
$tests->summary();
