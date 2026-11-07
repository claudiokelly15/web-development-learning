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
    case IdentityRecorded = 'identity.recorded';
    case IdentityChanged = 'identity.changed';
    case ContactCreated = 'contact.created';
    case ContactChanged = 'contact.changed';
    case OtpRequested = 'otp.requested';
    case OtpChanged = 'otp.changed';
    case OtpVerified = 'otp.verified';
    case ContactProofChanged = 'contact.proof_changed';
}
