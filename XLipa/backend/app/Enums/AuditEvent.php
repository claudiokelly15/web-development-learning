<?php

namespace App\Enums;

enum AuditEvent: string
{
    case MerchantCreated = 'merchant.created';
    case MembershipRevoked = 'membership.revoked';
    case ApplicationSubmitted = 'application.submitted';
    case ApplicationReviewed = 'application.reviewed';
    case PlatformRoleGranted = 'platform_role.granted';
    case PlatformRoleRevoked = 'platform_role.revoked';
    case AccountStatusChanged = 'account.status_changed';
    case MerchantStatusChanged = 'merchant.status_changed';
}
