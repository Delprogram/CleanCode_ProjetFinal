<?php

declare(strict_types=1);

final class BookingService
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $paymentGateways;

    /** @var BookingConfirmationListenerInterface[] */
    private array $confirmationListeners;

    public function __construct(
        private readonly ?PricingService $pricingService = null,
        ?array $paymentGateways = null,
        ?array $confirmationListeners = null
    ) {
        $this->paymentGateways = $paymentGateways ?? [
            'stripe' => new SupervisedPaymentGateway(new StripePaymentGateway()),
            'payfast' => new SupervisedPaymentGateway(new PayFastAdapter()),
        ];
        $this->confirmationListeners = $confirmationListeners ?? [
            new EmailConfirmationListener(),
            new LoyaltyPointsListener(),
            new AnalyticsTrackingListener(),
            new SmsNotificationListener(),
        ];
    }

    private function getPricingService(): PricingService
    {
        return $this->pricingService ?? new PricingService();
    }

    public function confirm(Booking $booking, string $paymentMethod = 'stripe'): float
    {
        if (count($booking->items) === 0) {
            throw new RuntimeException('Empty booking');
        }

        if (!filter_var($booking->customer->email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email');
        }

        $total = $this->getPricingService()->calculate($booking);

        $gateway = $this->paymentGateways[$paymentMethod] ?? throw new RuntimeException('Unknown payment method');
        $transactionId = $gateway->charge($total, (string) $booking->id);
        echo "PAYMENT {$transactionId}" . PHP_EOL;

        $booking->status = 'confirmed';

        echo "SQL INSERT booking={$booking->id} total={$total} status={$booking->status}" . PHP_EOL;

        foreach ($this->confirmationListeners as $listener) {
            $listener->onBookingConfirmed($booking, $total);
        }

        return $total;
    }
}
