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
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
        case 'pending':
        case 'awaiting_payment':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300';
        case 'failed':
        case 'cancelled':
            return 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-slate-400';
    }
}

export default function CustomerTransactions({ transactions, linked }: Props) {
    return (
        <CustomerLayout title="Transactions">
            <Head title="Transactions" />

            {!linked && (
                <div className="mb-6 p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/60 rounded-lg flex items-start gap-3">
                    <AlertCircle className="h-5 w-5 text-amber-500 dark:text-amber-400 mt-0.5 shrink-0" />
                    <p className="text-sm text-amber-900 dark:text-amber-200">
                        Your account isn&apos;t linked to a CRM customer yet, so no transactions are available.
                    </p>
                </div>
            )}

            {linked && transactions.length === 0 && (
                <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-8 text-center">
                    <CreditCard className="h-8 w-8 text-gray-300 dark:text-slate-600 mx-auto mb-3" />
                    <p className="text-sm text-gray-500 dark:text-slate-400">No transactions yet.</p>
                </div>
            )}

            {transactions.length > 0 && (
                <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 dark:bg-slate-800/40 border-b border-gray-200 dark:border-slate-800">
                            <tr>
                                <th className="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-slate-400">Reference</th>
                                <th className="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-slate-400">Status</th>
                                <th className="px-4 py-2.5 text-right font-medium text-gray-600 dark:text-slate-400">Amount</th>
                                <th className="px-4 py-2.5 text-right font-medium text-gray-600 dark:text-slate-400">Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-slate-800">
                            {transactions.map((t, idx) => (
                                <tr key={t.id ?? t.reference ?? idx} className="hover:bg-gray-50 dark:hover:bg-slate-800/40">
                                    <td className="px-4 py-3 font-mono text-xs text-gray-700 dark:text-slate-300">
                                        {t.reference ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`text-xs px-2 py-1 rounded font-medium capitalize ${statusClass(t.status)}`}>
                                            {(t.status ?? 'unknown').replace(/_/g, ' ')}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                        {fmtAmount(t.total, t.currency)}
                                    </td>
                                    <td className="px-4 py-3 text-right text-xs text-gray-500 dark:text-slate-400">
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
