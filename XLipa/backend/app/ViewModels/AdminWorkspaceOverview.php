<?php

namespace App\ViewModels;

use App\Enums\PlatformRole;

/** Only the signed-in account and its own resolved authority; no platform records. */
final readonly class AdminWorkspaceOverview
{
    public function __construct(
        public string $accountName,
        public string $accountEmail,
        public PlatformRole $role,
        public bool $accessPage = false,
    ) {}

    public function chief(): bool
    {
        return $this->role === PlatformRole::ChiefAdmin;
    }

    public function roleLabel(): string
    {
        return $this->chief() ? 'Chief Admin' : 'Staff Admin';
    }

    public function overviewRoute(): string
    {
        return $this->chief() ? 'admin.chief' : 'admin.staff';
    }

    /** @return list<string> */
    public function unavailableNavigation(): array
    {
        return $this->chief()
            ? ['Merchant Management', 'Merchant Applications', 'Transactions', 'Payment Links', 'Wallet and Ledger', 'Withdrawals', 'Settlements', 'Revenue and Fees', 'Payment Providers', 'API Management', 'Risk and Fraud', 'Disputes and Refunds', 'Staff Management', 'Audit Logs', 'System Settings']
            : ['Merchant Applications', 'Transactions', 'Audit Logs'];
    }

    /** @return list<array{label:string, state:string}> */
    public function capabilities(): array
    {
        return [
            ['label' => 'View Chief Admin overview', 'state' => $this->chief() ? 'Available' : 'Not permitted'],
            ['label' => 'View Staff Admin overview', 'state' => $this->chief() ? 'Not permitted' : 'Available'],
            ['label' => 'View your own access scope', 'state' => 'Available'],
            ['label' => 'Browse merchant or application records', 'state' => $this->chief() ? 'Redacted application preparation only' : 'Unavailable'],
            ['label' => 'Approve or reject applications', 'state' => 'Unavailable'],
            ['label' => 'View transactions, balances or settlements', 'state' => 'Unavailable'],
            ['label' => 'Manage staff or system settings', 'state' => 'Unavailable'],
            ['label' => 'View audit records or support cases', 'state' => 'Unavailable'],
        ];
    }
}
