<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CrmPaymentService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class TransactionController extends Controller
{
    public function __construct(
        private readonly CrmPaymentService $paymentService,
    ) {}

    public function index(): Response
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $transactions = $customer->isLinkedToCrm()
            ? $this->paymentService->historyForCustomer($customer->crm_customer_id)
            : [];

        return Inertia::render('Account/Transactions', [
            'transactions' => $transactions,
            'linked' => $customer->isLinkedToCrm(),
        ]);
    }
}
