<?php

declare(strict_types=1);

namespace App\Enums;

enum ComplianceRecipient: string
{
    case CUSTOMER = 'customer';
    case CUSTOMER_AND_MECHANIC = 'customer_and_mechanic';
    case MECHANIC = 'mechanic';
}
