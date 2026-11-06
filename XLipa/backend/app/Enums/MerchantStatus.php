<?php

namespace App\Enums;

enum MerchantStatus: string
{
    case Onboarding = 'onboarding';
    case Enabled = 'enabled';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
