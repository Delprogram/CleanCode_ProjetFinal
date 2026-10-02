<?php

declare(strict_types=1);

final class StripePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly ?StripeClient $client = null
    ) {
    }

    private function getClient(): StripeClient
    {
        return $this->client ?? new StripeClient();
    }

    public function charge(float $amount, string $reference = ''): string
    {
        return $this->getClient()->charge($amount);
    }
}
