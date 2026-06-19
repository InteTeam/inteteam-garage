<?php

declare(strict_types=1);

namespace App\Enums;

enum ComplianceType: string
{
    case MOT = 'mot';
    case TAX = 'tax';
    case INSURANCE = 'insurance';
}
