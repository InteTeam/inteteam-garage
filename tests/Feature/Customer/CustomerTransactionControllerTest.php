<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CustomerTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlinked_customer_sees_empty_list_with_banner(): void
    {
        $customer = Customer::create([
            'email' => 'orphan@example.com',
            'name' => 'Orphan',
            'crm_customer_id' => null,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.transactions'))
            ->assertOk()
            ->assertInertia(
                fn ($p) => $p
                    ->component('Account/Transactions')
                    ->where('linked', false)
                    ->where('transactions', []),
            );
    }

    public function test_linked_customer_sees_transactions_from_crm(): void
    {
        $customer = Customer::create([
            'email' => 'jane@example.com',
            'name' => 'Jane',
            'crm_customer_id' => 'crm-jane',
        ]);

        Http::fake([
            '*/api/v1/internal/payments?*' => Http::response([
                'data' => [
                    ['id' => 'p1', 'reference' => 'REF-001', 'total' => 120.00, 'status' => 'paid', 'paid_at' => '2026-06-01T10:00:00Z'],
                    ['id' => 'p2', 'reference' => 'REF-002', 'total' => 350.50, 'status' => 'pending', 'paid_at' => null],
                ],
            ], 200),
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.transactions'))
            ->assertOk()
            ->assertInertia(
                fn ($p) => $p
                    ->where('linked', true)
                    ->has('transactions', 2)
                    ->where('transactions.0.reference', 'REF-001')
                    ->where('transactions.1.status', 'pending'),
            );
    }

    public function test_crm_error_returns_empty_list_not_500(): void
    {
        $customer = Customer::create([
            'email' => 'jane@example.com',
            'name' => 'Jane',
            'crm_customer_id' => 'crm-jane',
        ]);

        Http::fake([
            '*/api/v1/internal/payments?*' => Http::response('boom', 503),
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.transactions'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('transactions', []));
    }
}
