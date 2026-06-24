import { Head } from '@inertiajs/react';
import { AlertCircle, CreditCard } from 'lucide-react';
import CustomerLayout from '@/Layouts/CustomerLayout';

interface Transaction {
    id?: string;
    reference?: string;
    job_id?: string;
    total?: number | string;
    currency?: string;
    status?: string;
    created_at?: string;
    paid_at?: string | null;
}

interface Props {
    transactions: Transaction[];
    linked: boolean;
}

function fmtAmount(amount: number | string | undefined, currency: string | undefined): string {
    if (amount === undefined || amount === null) return '—';
    const n = Number(amount);
    if (Number.isNaN(n)) return String(amount);
    const cur = currency || 'GBP';
    try {
        return new Intl.NumberFormat('en-GB', { style: 'currency', currency: cur }).format(n);
    } catch {
        return `${cur} ${n.toFixed(2)}`;
    }
}

function statusClass(status: string | undefined): string {
    switch ((status ?? '').toLowerCase()) {
        case 'paid':
        case 'completed':
        case 'confirmed':
            return 'bg-emerald-100 text-emerald-700';
        case 'pending':
        case 'awaiting_payment':
            return 'bg-amber-100 text-amber-700';
        case 'failed':
        case 'cancelled':
            return 'bg-red-100 text-red-700';
        default:
            return 'bg-gray-100 text-gray-600';
    }
}

export default function CustomerTransactions({ transactions, linked }: Props) {
    return (
        <CustomerLayout title="Transactions">
            <Head title="Transactions" />

            {!linked && (
                <div className="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-3">
                    <AlertCircle className="h-5 w-5 text-amber-500 mt-0.5 shrink-0" />
                    <p className="text-sm text-amber-900">
                        Your account isn&apos;t linked to a CRM customer yet, so no transactions are available.
                    </p>
                </div>
            )}

            {linked && transactions.length === 0 && (
                <div className="bg-white rounded-lg border border-gray-200 p-8 text-center">
                    <CreditCard className="h-8 w-8 text-gray-300 mx-auto mb-3" />
                    <p className="text-sm text-gray-500">No transactions yet.</p>
                </div>
            )}

            {transactions.length > 0 && (
                <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="px-4 py-2.5 text-left font-medium text-gray-600">Reference</th>
                                <th className="px-4 py-2.5 text-left font-medium text-gray-600">Status</th>
                                <th className="px-4 py-2.5 text-right font-medium text-gray-600">Amount</th>
                                <th className="px-4 py-2.5 text-right font-medium text-gray-600">Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {transactions.map((t, idx) => (
                                <tr key={t.id ?? t.reference ?? idx} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono text-xs text-gray-700">
                                        {t.reference ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`text-xs px-2 py-1 rounded font-medium capitalize ${statusClass(t.status)}`}>
                                            {(t.status ?? 'unknown').replace(/_/g, ' ')}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium text-gray-900">
                                        {fmtAmount(t.total, t.currency)}
                                    </td>
                                    <td className="px-4 py-3 text-right text-xs text-gray-500">
                                        {t.paid_at
                                            ? new Date(t.paid_at).toLocaleDateString()
                                            : t.created_at
                                                ? new Date(t.created_at).toLocaleDateString()
                                                : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </CustomerLayout>
    );
}
