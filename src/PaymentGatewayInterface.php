<?php

declare(strict_types=1);

interface PaymentGatewayInterface
{
    /**
     * Effectue le paiement et retourne l'identifiant unique de transaction.
     *
     * @throws RuntimeException En cas d'échec de paiement ou de montant invalide
     */
    public function charge(float $amount, string $reference = ''): string;
}
