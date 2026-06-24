<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'crm_customer_id' => null,
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'last_login_at' => null,
        ];
    }

    public function linked(?string $crmCustomerId = null): self
    {
        return $this->state(fn () => [
            'crm_customer_id' => $crmCustomerId ?? 'crm-' . $this->faker->bothify('????####'),
        ]);
    }
}
