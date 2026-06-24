<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $crm_customer_id
 * @property string $email
 * @property string $name
 * @property Carbon|null $last_login_at
 */
#[Fillable(['crm_customer_id', 'email', 'name', 'last_login_at'])]
final class Customer extends Authenticatable
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    public function isLinkedToCrm(): bool
    {
        return $this->crm_customer_id !== null && $this->crm_customer_id !== '';
    }
}
