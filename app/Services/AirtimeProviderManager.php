<?php

namespace App\Services;

class AirtimeProviderManager
{
    public function __construct(
        private TopupboxService $topupbox,
        private MtnHostIfService $mtnHostIf
    ) {}

    public function activeProvider(): string
    {
        return strtolower((string) config('services.airtime.provider', 'topupbox'));
    }

    public function purchaseAirtime(string $phone, float $amount, string $network): array
    {
        return $this->providerFor($network)->purchaseAirtime($phone, $amount, $network);
    }

    public function purchaseData(string $phone, float $amount, string $network, string $tariffTypeId): array
    {
        return $this->providerFor($network)->purchaseData($phone, $amount, $network, $tariffTypeId);
    }

    public function getBalance(): array
    {
        return $this->provider()->getBalance();
    }

    public function requiresMerchantBalanceCheck(): bool
    {
        return (bool) config('services.airtime.require_merchant_balance_check', $this->activeProvider() === 'topupbox');
    }

    private function providerFor(string $network): TopupboxService|MtnHostIfService
    {
        if ($this->activeProvider() === 'mtn' && strtolower($network) !== 'mtn') {
            return $this->topupbox;
        }

        return $this->provider();
    }

    private function provider(): TopupboxService|MtnHostIfService
    {
        return match ($this->activeProvider()) {
            'mtn' => $this->mtnHostIf,
            default => $this->topupbox,
        };
    }
}
