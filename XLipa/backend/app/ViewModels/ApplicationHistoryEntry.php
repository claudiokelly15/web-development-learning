<?php

namespace App\ViewModels;

/** Explicit redacted projection shared by owner history and Chief preparation. */
final class ApplicationHistoryEntry
{
    public static function label(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'pending_review' => 'Submitted / awaiting review',
            'information_requested' => 'Additional information required',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => 'Status unavailable',
        };
    }
}
