<?php

declare(strict_types=1);

namespace App\Enums;

enum ComplianceSource: string
{
    case MANUAL = 'manual';
    case DVLA = 'dvla';
    case DVSA = 'dvsa';
}
