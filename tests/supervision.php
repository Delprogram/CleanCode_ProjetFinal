<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestRunner.php';

$tests = new TestRunner();

// 1. Test du succès de supervision (avec Stripe)
$logs = [];
$logger = function (string $msg) use (&$logs): void {
    $logs[] = $msg;
};

$stripeGateway = new StripePaymentGateway();
$supervisedStripe = new SupervisedPaymentGateway($stripeGateway, $logger);

$txId = $supervisedStripe->charge(120.50, 'ref_stripe_1');
$tests->same('stripe_120.50', $txId, 'Supervised gateway returns correct transaction id');

$tests->same(2, count($logs), 'Supervised gateway logs start and end messages on success');
$tests->true(str_contains($logs[0], 'SUPERVISION [PAYMENT START] amount=120.5'), 'Log contains START with amount');
$tests->true(str_contains($logs[1], 'SUPERVISION [PAYMENT SUCCESS] amount=120.5'), 'Log contains SUCCESS with amount');
$tests->true(str_contains($logs[1], 'duration='), 'Log contains measured duration in ms');
$tests->true(str_contains($logs[1], 'tx=stripe_120.50'), 'Log contains transaction id');

// 2. Test de l'échec de supervision
$failLogs = [];
$failLogger = function (string $msg) use (&$failLogs): void {
    $failLogs[] = $msg;
};

$supervisedFail = new SupervisedPaymentGateway($stripeGateway, $failLogger);

$tests->throws(
    fn() => $supervisedFail->charge(-10.0),
    RuntimeException::class,
    'Invalid amount',
    'Supervised gateway propagates payment exception'
);

$tests->same(2, count($failLogs), 'Supervised gateway logs start and failure messages on error');
$tests->true(str_contains($failLogs[0], 'SUPERVISION [PAYMENT START] amount=-10'), 'Failure log contains START');
$tests->true(str_contains($failLogs[1], 'SUPERVISION [PAYMENT FAILURE] amount=-10'), 'Failure log contains FAILURE');
$tests->true(str_contains($failLogs[1], 'error=Invalid amount'), 'Failure log contains error detail');
$tests->true(str_contains($failLogs[1], 'duration='), 'Failure log contains duration');

// 3. Test de supervision avec PayFast
$payfastLogs = [];
$payfastLogger = function (string $msg) use (&$payfastLogs): void {
    $payfastLogs[] = $msg;
};

$payfastAdapter = new PayFastAdapter();
$supervisedPayfast = new SupervisedPaymentGateway($payfastAdapter, $payfastLogger);

$payfastTx = $supervisedPayfast->charge(99.00, 'ref_pf_99');
$tests->same('payfast_ref_pf_99', $payfastTx, 'Supervised PayFast returns correct tx id');
$tests->true(str_contains($payfastLogs[1], 'SUPERVISION [PAYMENT SUCCESS]'), 'PayFast logs SUCCESS');

$tests->summary();

