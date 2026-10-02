<?php

declare(strict_types=1);

final class SupervisedPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @param PaymentGatewayInterface $inner La passerelle de paiement décorée
     * @param (callable(string): void)|null $logger Callback de log optionnel (par défaut: echo)
     */
    private $logger;

    public function __construct(
        private readonly PaymentGatewayInterface $inner,
        ?callable $logger = null
    ) {
        $this->logger = $logger;
    }

    private function log(string $message): void
    {
        if (is_callable($this->logger)) {
            ($this->logger)($message);
            return;
        }

        echo $message . PHP_EOL;
    }

    public function charge(float $amount, string $reference = ''): string
    {
        $startTime = microtime(true);
        $this->log("SUPERVISION [PAYMENT START] amount={$amount} ref={$reference}");

        try {
            $transactionId = $this->inner->charge($amount, $reference);
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            $this->log("SUPERVISION [PAYMENT SUCCESS] amount={$amount} duration={$durationMs}ms tx={$transactionId}");
            return $transactionId;
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            $this->log("SUPERVISION [PAYMENT FAILURE] amount={$amount} duration={$durationMs}ms error={$e->getMessage()}");
            throw $e;
        }
    }
}
