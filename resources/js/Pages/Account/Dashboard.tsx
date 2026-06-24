import { Head, Link } from '@inertiajs/react';
import { AlertCircle, Car, Wrench } from 'lucide-react';
import CustomerLayout from '@/Layouts/CustomerLayout';

type ComplianceType = 'mot' | 'tax' | 'insurance';

interface ComplianceSummary {
    expires_on: string | null;
}

interface VehicleSummary {
    id: string;
    registration: string;
    make: string;
    model: string;
    garage_name: string | null;
    compliance: Record<ComplianceType, ComplianceSummary | null>;
}

interface JobSummary {
    id: string;
    state: string;
    updated_at: string | null;
    vehicle: { registration: string; make: string; model: string };
    garage_name: string | null;
}

interface Props {
    customer: { name: string; email: string };
    linked: boolean;
    vehicles: VehicleSummary[];
    recentJobs: JobSummary[];
}

const COMPLIANCE_LABELS: Record<ComplianceType, string> = {
    mot: 'MOT',
    tax: 'Tax',
    insurance: 'Insurance',
};

function daysUntil(date: string | null): number | null {
    if (!date) return null;
    const target = new Date(date + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.floor((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
}

function complianceClass(days: number | null): string {
    if (days === null) return 'bg-gray-100 text-gray-500';
    if (days < 0) return 'bg-red-100 text-red-700';
    if (days <= 30) return 'bg-amber-100 text-amber-700';
    return 'bg-emerald-100 text-emerald-700';
}

function complianceLabel(days: number | null): string {
    if (days === null) return '—';
    if (days < 0) return 'expired';
    if (days === 0) return 'today';
    return `${days}d`;
}

function jobStateLabel(state: string): string {
    return state.replace(/_/g, ' ');
}

export default function CustomerDashboard({ customer, linked, vehicles, recentJobs }: Props) {
    return (
        <CustomerLayout title={`Welcome${customer.name ? `, ${customer.name.split(' ')[0]}` : ''}`}>
            <Head title="My Account" />

            {!linked && (
                <div className="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-3">
                    <AlertCircle className="h-5 w-5 text-amber-500 mt-0.5 shrink-0" />
                    <div>
                        <p className="text-sm font-medium text-amber-900">Your account isn&apos;t linked to a garage yet.</p>
                        <p className="text-xs text-amber-700 mt-1">
                            Once a mechanic creates a job for the email <code className="font-mono">{customer.email}</code>,
                            it will appear here automatically.
                        </p>
                    </div>
                </div>
            )}

            <section className="mb-8">
                <div className="flex items-center gap-2 mb-3">
                    <Car className="h-4 w-4 text-gray-500" />
                    <h2 className="text-sm font-semibold text-gray-700">My vehicles</h2>
                </div>
                {vehicles.length === 0 ? (
                    <div className="bg-white rounded-lg border border-gray-200 p-6 text-center text-sm text-gray-500">
                        No vehicles on file.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {vehicles.map((v) => (
                            <Link
                                key={v.id}
                                href={`/account/vehicles/${v.id}`}
                                className="bg-white rounded-lg border border-gray-200 p-4 hover:border-gray-300 transition-colors"
                            >
                                <div className="flex items-start justify-between mb-3">
                                    <div>
                                        <p className="font-semibold text-gray-900">{v.registration}</p>
                                        <p className="text-xs text-gray-500">{v.make} {v.model}</p>
                                    </div>
                                    {v.garage_name && (
                                        <span className="text-xs text-gray-400">{v.garage_name}</span>
                                    )}
                                </div>
                                <div className="flex gap-1.5">
                                    {(['mot', 'tax', 'insurance'] as const).map((t) => {
                                        const days = daysUntil(v.compliance[t]?.expires_on ?? null);
                                        return (
                                            <span
                                                key={t}
                                                className={`text-xs px-2 py-0.5 rounded font-medium ${complianceClass(days)}`}
                                            >
                                                {COMPLIANCE_LABELS[t]} · {complianceLabel(days)}
                                            </span>
                                        );
                                    })}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </section>

            <section>
                <div className="flex items-center gap-2 mb-3">
                    <Wrench className="h-4 w-4 text-gray-500" />
                    <h2 className="text-sm font-semibold text-gray-700">Recent jobs</h2>
                </div>
                {recentJobs.length === 0 ? (
                    <div className="bg-white rounded-lg border border-gray-200 p-6 text-center text-sm text-gray-500">
                        No jobs yet.
                    </div>
                ) : (
                    <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th className="px-4 py-2 text-left font-medium text-gray-600">Vehicle</th>
                                    <th className="px-4 py-2 text-left font-medium text-gray-600">State</th>
                                    <th className="px-4 py-2 text-left font-medium text-gray-600">Garage</th>
                                    <th className="px-4 py-2 text-right font-medium text-gray-600">Updated</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {recentJobs.map((j) => (
                                    <tr key={j.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-2.5">
                                            <Link
                                                href={`/account/jobs/${j.id}`}
                                                className="text-blue-600 hover:text-blue-800 font-medium"
                                            >
                                                {j.vehicle.registration}
                                            </Link>
                                            <p className="text-xs text-gray-500">{j.vehicle.make} {j.vehicle.model}</p>
                                        </td>
                                        <td className="px-4 py-2.5 text-gray-700 capitalize">{jobStateLabel(j.state)}</td>
                                        <td className="px-4 py-2.5 text-gray-500">{j.garage_name ?? '—'}</td>
                                        <td className="px-4 py-2.5 text-right text-xs text-gray-400">
                                            {j.updated_at ? new Date(j.updated_at).toLocaleDateString() : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </CustomerLayout>
    );
}
