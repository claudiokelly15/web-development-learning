<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case NeedsInformation = 'needs_information';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
