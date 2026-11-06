<?php

namespace App\Enums;

enum MerchantRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';
}
