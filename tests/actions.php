<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestRunner.php';

$tests = new TestRunner();

$customerWithPhone = new Customer(101, 'alex@example.com', '0611223344', 'standard');
$customerWithoutPhone = new Customer(102, 'no.phone@example.com', null, 'standard');

$bookingWithPhone = new Booking(2001, $customerWithPhone, 'day');
$bookingWithoutPhone = new Booking(2002, $customerWithoutPhone, 'day');

// 1. Test unitaire EmailConfirmationListener
ob_start();
$emailListener = new EmailConfirmationListener();
$emailListener->onBookingConfirmed($bookingWithPhone, 100.0);
$outputEmail = ob_get_clean();
$tests->true(str_contains($outputEmail, 'EMAIL alex@example.com: booking 2001 confirmed'), 'Email listener sends confirmation email');

// 2. Test unitaire LoyaltyPointsListener
ob_start();
$loyaltyListener = new LoyaltyPointsListener();
$loyaltyListener->onBookingConfirmed($bookingWithPhone, 149.90);
$outputLoyalty = ob_get_clean();
$tests->true(str_contains($outputLoyalty, 'LOYALTY customer=101 points=149'), 'Loyalty listener awards 1 point per whole euro spent');

// 3. Test unitaire AnalyticsTrackingListener
ob_start();
$analyticsListener = new AnalyticsTrackingListener();
$analyticsListener->onBookingConfirmed($bookingWithPhone, 100.0);
$outputAnalytics = ob_get_clean();
$tests->true(str_contains($outputAnalytics, 'ANALYTICS booking_confirmed'), 'Analytics listener tracks confirmation event');
$tests->true(str_contains($outputAnalytics, '"booking_id":2001'), 'Analytics data includes booking id');
$tests->true(str_contains($outputAnalytics, '"total":100'), 'Analytics data includes total amount');

// 4. Test unitaire SmsNotificationListener (avec téléphone)
ob_start();
$smsListener = new SmsNotificationListener();
$smsListener->onBookingConfirmed($bookingWithPhone, 100.0);
$outputSmsWithPhone = ob_get_clean();
$tests->true(str_contains($outputSmsWithPhone, 'SMS 0611223344:'), 'SMS listener sends SMS when phone number is present');

// 5. Test unitaire SmsNotificationListener (SANS téléphone)
ob_start();
$smsListener->onBookingConfirmed($bookingWithoutPhone, 100.0);
$outputSmsWithoutPhone = ob_get_clean();
$tests->same('', trim($outputSmsWithoutPhone), 'SMS listener does NOT send SMS when phone is null');

// 6. Test d'intégration complet avec BookingService
ob_start();
$booking = new Booking(3001, $customerWithPhone, 'day');
$booking->addItem(new BookingItem(new Ticket('CONCERT', 'Pass Concert', 50.0), 2));

$service = new BookingService();
$service->confirm($booking, 'stripe');
$serviceOutput = ob_get_clean();

$tests->true(str_contains($serviceOutput, 'EMAIL alex@example.com:'), 'BookingService triggers Email listener');
$tests->true(str_contains($serviceOutput, 'LOYALTY customer=101'), 'BookingService triggers Loyalty listener');
$tests->true(str_contains($serviceOutput, 'ANALYTICS booking_confirmed'), 'BookingService triggers Analytics listener');
$tests->true(str_contains($serviceOutput, 'SMS 0611223344:'), 'BookingService triggers SMS listener');

// 7. Test d'extensibilité OCP (Exemple Slack / Variante C)
class MockSlackListener implements BookingConfirmationListenerInterface
{
    public bool $called = false;
    public function onBookingConfirmed(Booking $booking, float $total): void
    {
        $this->called = true;
    }
}

$slackListener = new MockSlackListener();
$customService = new BookingService(
    confirmationListeners: [$slackListener]
);

ob_start();
$customService->confirm($booking, 'stripe');
ob_end_clean();

$tests->true($slackListener->called, 'BookingService supports adding new reaction listeners without modifying its code (OCP)');

$tests->summary();
