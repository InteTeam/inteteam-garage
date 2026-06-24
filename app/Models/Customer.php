<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $crm_customer_id
 * @property string $email
 * @property string $name
 * @property Carbon|null $last_login_at
 */
final class Customer extends Authenticatable
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'crm_customer_id',
        'email',
        'name',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Force lowercase + trim on every email write. SSO callback already folds
     * the case, but the invariant should live on the model — factories,
     * seeders, and incident-response SQL all need to preserve it for the
     * email-unique index and the lookup-by-email path to agree.
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    public function isLinkedToCrm(): bool
    {
        return $this->crm_customer_id !== null && $this->crm_customer_id !== '';
    }
}
