<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case InformationRequested = 'information_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
