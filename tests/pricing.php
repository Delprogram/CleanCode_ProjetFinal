<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestRunner.php';

$tests = new TestRunner();
$pricing = new PricingService();

function createTestBooking(string $customerType, string $passType, float $unitPrice, int $qty): Booking
{
    $customer = new Customer(1, 'client@example.com', null, $customerType);
    $booking = new Booking(1, $customer, $passType);
    $booking->addItem(new BookingItem(new Ticket('T1', 'Billet', $unitPrice), $qty));
    return $booking;
}

// 1. Client standard : aucune remise statut
$stdBooking = createTestBooking('standard', 'day', 150.0, 1);
$tests->near(150.0, $pricing->calculate($stdBooking), 'Standard customer: 0% discount');

// 2. VIP < 100 € : 5 % de remise
$vipUnder100 = createTestBooking('vip', 'day', 80.0, 1);
// 80 * 0.95 = 76.0
$tests->near(76.0, $pricing->calculate($vipUnder100), 'VIP under 100 euros: 5% discount');

// 3. VIP frontière 100 € : 10 % de remise
$vipAt100 = createTestBooking('vip', 'day', 100.0, 1);
// 100 * 0.90 = 90.0
$tests->near(90.0, $pricing->calculate($vipAt100), 'VIP at 100 euros: 10% discount');

// 4. VIP entre 100 € et 299.99 € : 10 % de remise
$vipMid = createTestBooking('vip', 'day', 200.0, 1);
// 200 * 0.90 = 180.0
$tests->near(180.0, $pricing->calculate($vipMid), 'VIP between 100 and 299.99 euros: 10% discount');

// 5. VIP frontière 300 € : 15 % de remise
$vipAt300 = createTestBooking('vip', 'day', 300.0, 1);
// 300 * 0.85 = 255.0
$tests->near(255.0, $pricing->calculate($vipAt300), 'VIP at 300 euros: 15% discount');

// 6. VIP > 300 € : 15 % de remise
$vipAbove300 = createTestBooking('vip', 'day', 400.0, 1);
// 400 * 0.85 = 340.0
$tests->near(340.0, $pricing->calculate($vipAbove300), 'VIP over 300 euros: 15% discount');

// 7. Pass 3 jours : déduction de 20 €
$pass3DaysStd = createTestBooking('standard', '3days', 80.0, 1);
// 80 - 20 = 60.0
$tests->near(60.0, $pricing->calculate($pass3DaysStd), '3-day pass: 20 euros deduction');

// 8. Combinaison VIP [100-299.99] + Pass 3 jours
$vipPass3Days = createTestBooking('vip', '3days', 100.0, 1);
// (100 * 0.90) - 20 = 70.0
$tests->near(70.0, $pricing->calculate($vipPass3Days), 'VIP 100e + 3-day pass: 10% discount then -20 euros');

// 9. Règle du montant non négatif (plancher à 0 €)
$smallBookingWith3Days = createTestBooking('standard', '3days', 15.0, 1);
// 15 - 20 = -5 -> doit être ramené à 0.0
$tests->near(0.0, $pricing->calculate($smallBookingWith3Days), 'Total cannot be negative: clamped to 0');

$tests->summary();
