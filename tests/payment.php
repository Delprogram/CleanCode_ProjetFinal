<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestRunner.php';

$tests = new TestRunner();

// 1. Test unitaire StripePaymentGateway
$stripeGateway = new StripePaymentGateway();
$stripeTx = $stripeGateway->charge(100.0, 'test_ref_1');
$tests->same('stripe_100.00', $stripeTx, 'StripePaymentGateway charges correct amount');

$tests->throws(
    fn () => $stripeGateway->charge(0.0),
    RuntimeException::class,
    'Invalid amount',
    'StripePaymentGateway throws on zero/negative amount'
);

// 2. Test unitaire PayFastAdapter
$payfastGateway = new PayFastAdapter();
$payfastTx = $payfastGateway->charge(75.50, 'booking_42');
$tests->same('payfast_booking_42', $payfastTx, 'PayFastAdapter charges and formats transaction id');

$tests->throws(
    fn () => $payfastGateway->charge(0.0),
    RuntimeException::class,
    'Invalid amount',
    'PayFastAdapter throws on zero/negative amount'
);

// 3. Test intégration dans BookingService avec PayFast
ob_start();
$service = new BookingService();

$customer = new Customer(10, 'payfast.client@example.com', null, 'standard');
$booking = new Booking(500, $customer, 'day');
$booking->addItem(new BookingItem(new Ticket('VIP-PASS', 'Billet', 100.0), 1));

$total = $service->confirm($booking, 'payfast');
$output = ob_get_clean();

$tests->near(100.0, $total, 'Booking with PayFast calculates correct total');
$tests->same('confirmed', $booking->status, 'Booking with PayFast becomes confirmed');
$tests->true(str_contains($output, 'PAYMENT payfast_500'), 'Booking logs PayFast payment transaction');

// 4. Test méthode inconnue
$tests->throws(
    fn () => $service->confirm($booking, 'crypto_unsupported'),
    RuntimeException::class,
    'Unknown payment method',
    'Unknown payment method throws RuntimeException'
);

$tests->summary();
