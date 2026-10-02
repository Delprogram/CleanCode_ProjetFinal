<?php

declare(strict_types=1);

final class PayFastAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private readonly ?PayFastSdk $sdk = null
    ) {
    }

    private function getSdk(): PayFastSdk
    {
        return $this->sdk ?? new PayFastSdk();
    }

    public function charge(float $amount, string $reference = ''): string
    {
        if ($amount <= 0.0) {
            throw new RuntimeException('Invalid amount');
        }

        $amountCents = (int) round($amount * 100);
        $ref = $reference !== '' ? $reference : uniqid('booking_', true);

        $result = $this->getSdk()->executePayment([
            'reference' => $ref,
            'amount_cents' => $amountCents,
            'currency' => 'EUR',
        ]);

        if (empty($result['success']) || empty($result['transaction_id'])) {
            throw new RuntimeException('PayFast payment failed');
        }

        return $result['transaction_id'];
    }
}
