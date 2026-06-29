import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import CustomerLayout from '@/Layouts/CustomerLayout';
import { COMPLIANCE_LABELS, type ComplianceType } from '@/lib/compliance';

interface ComplianceRecord {
    id: string;
    type: ComplianceType;
    expires_on: string;
    note: string | null;
    source: 'manual' | 'dvla';
    created_at: string;
}

interface Vehicle {
    id: string;
    registration: string;
    make: string;
    model: string;
    year: number | null;
    colour: string | null;
    vin: string | null;
}

interface Props {
    vehicle: Vehicle;
    compliance: Record<ComplianceType, ComplianceRecord | null>;
    complianceHistory: ComplianceRecord[];
}

function daysUntil(date: string): number {
    const target = new Date(date + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.floor((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
}

function statusClass(record: ComplianceRecord | null): string {
    if (!record) return 'border-gray-200 bg-gray-50';
    const d = daysUntil(record.expires_on);
    if (d < 0) return 'border-red-300 bg-red-50';
    if (d <= 30) return 'border-amber-300 bg-amber-50';
    return 'border-emerald-300 bg-emerald-50';
}

function statusBadge(record: ComplianceRecord | null): { label: string; cls: string } {
    if (!record) return { label: 'Not recorded', cls: 'bg-gray-200 text-gray-600' };
    const d = daysUntil(record.expires_on);
    if (d < 0) return { label: `expired ${Math.abs(d)}d ago`, cls: 'bg-red-200 text-red-800' };
    if (d === 0) return { label: 'expires today', cls: 'bg-amber-200 text-amber-800' };
    if (d <= 30) return { label: `${d}d left`, cls: 'bg-amber-200 text-amber-800' };
    return { label: `${d}d left`, cls: 'bg-emerald-200 text-emerald-800' };
}

export default function CustomerVehicleShow({ vehicle, compliance, complianceHistory }: Props) {
    const [tab, setTab] = useState<'details' | 'compliance'>('details');

    return (
        <CustomerLayout title={vehicle.registration}>
            <Head title={`Vehicle · ${vehicle.registration}`} />

            <div className="mb-4">
                <Link
                    href="/account"
                    className="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700"
                >
                    <ArrowLeft className="h-3.5 w-3.5" /> Back
                </Link>
            </div>

            <div className="max-w-2xl">
                <div className="flex gap-1 border-b border-gray-200 mb-4" role="tablist">
                    {(['details', 'compliance'] as const).map((t) => (
                        <button
                            key={t}
                            type="button"
                            role="tab"
                            aria-selected={tab === t}
                            onClick={() => setTab(t)}
                            className={
                                'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ' +
                                (tab === t
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700')
                            }
                        >
                            {t === 'details' ? 'Details' : 'Compliance'}
                        </button>
                    ))}
                </div>

                {tab === 'details' && (
                    <div className="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                        <Detail label="Registration" value={vehicle.registration} />
                        <Detail label="Make / Model" value={`${vehicle.make} ${vehicle.model}`} />
                        <Detail label="Year" value={vehicle.year?.toString() ?? '—'} />
                        <Detail label="Colour" value={vehicle.colour ?? '—'} />
                        <Detail label="VIN" value={vehicle.vin ?? '—'} mono />
                    </div>
                )}

                {tab === 'compliance' && (
                    <div className="space-y-3">
                        {(['mot', 'tax', 'insurance'] as const).map((type) => {
                            const record = compliance[type];
                            const badge = statusBadge(record);
                            return (
                                <div
                                    key={type}
                                    className={`rounded-lg border p-4 ${statusClass(record)}`}
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="font-semibold text-gray-900">{COMPLIANCE_LABELS[type]}</p>
                                            <p className="text-xs text-gray-500 mt-0.5">
                                                {record ? `Expires ${record.expires_on}` : 'No record on file'}
                                            </p>
                                        </div>
                                        <span className={`text-xs px-2 py-1 rounded font-medium ${badge.cls}`}>
                                            {badge.label}
                                        </span>
                                    </div>
                                </div>
                            );
                        })}

                        {complianceHistory.length > 0 && (
                            <details className="bg-white rounded-lg border border-gray-200 p-4 mt-4">
                                <summary className="text-sm font-medium text-gray-700 cursor-pointer">
                                    History ({complianceHistory.length})
                                </summary>
                                <ul className="mt-3 space-y-2 text-xs text-gray-600">
                                    {complianceHistory.map((r) => (
                                        <li key={r.id} className="flex items-center gap-2">
                                            <span className="font-medium text-gray-900">
                                                {COMPLIANCE_LABELS[r.type]}
                                            </span>
                                            <span>expires {r.expires_on}</span>
                                            <span className="px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-600 uppercase">
                                                {r.source}
                                            </span>
                                            <span className="text-gray-400 ml-auto">
                                                {new Date(r.created_at).toLocaleDateString()}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        )}
                    </div>
                )}
            </div>
        </CustomerLayout>
    );
}

function Detail({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
    return (
        <div>
            <p className="text-xs font-medium uppercase text-gray-500 tracking-wide">{label}</p>
            <p className={`text-sm text-gray-900 mt-0.5 ${mono ? 'font-mono' : ''}`}>{value}</p>
        </div>
    );
}
