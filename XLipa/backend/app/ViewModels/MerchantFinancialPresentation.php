<?php

namespace App\ViewModels;

use DateTimeImmutable;
use DateTimeZone;

final class MerchantFinancialPresentation
{
    public static function tzs(int $amount): string
    {
        return 'TZS '.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', (string) $amount);
    }

    public static function time(?int $milliseconds): string
    {
        return $milliseconds === null ? 'Not recorded' : (new DateTimeImmutable('@'.intdiv($milliseconds, 1000)))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('d M Y, H:i:s').' EAT';
    }

    public static function status(string $status): string
    {
        return match ($status) {
            'pending' => 'Payment pending',
            'confirmed' => 'Fictional payment confirmed',
            'failed' => 'Mock payment failed',
            'unknown', 'unresolved' => 'Payment unresolved',
            'posted' => 'Ledger posted',
            'not_posted' => 'Ledger not yet posted',
            'not_eligible' => 'No confirmed accounting source',
            default => 'Status unavailable',
        };
    }
}
