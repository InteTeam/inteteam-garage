import { Head, Link, router, useForm } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { COMPLIANCE_LABELS, type ComplianceType } from '@/lib/compliance';
import { Pencil, RefreshCw } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Vehicle {
    id: string;
    crm_customer_id: string;
    registration: string;
    vin: string | null;
    make: string;
    model: string;
    year: number | null;
    colour: string | null;
    created_at: string;
}

interface ComplianceRecord {
    id: string;
    type: ComplianceType;
    source: 'manual' | 'dvla' | 'dvsa';
    expires_on: string;
    note: string | null;
    created_at: string;
    recorded_by?: { id: string; name: string } | null;
}

interface Props {
    vehicle: Vehicle;
    compliance: Record<ComplianceType, ComplianceRecord | null>;
    complianceHistory: ComplianceRecord[];
    dvlaEnabled: boolean;
}

const labelClass = 'text-xs uppercase tracking-wide text-gray-500 dark:text-slate-400 font-medium';
const valueClass = 'text-sm text-gray-900 dark:text-white mt-0.5';

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-GB');
}

function daysUntil(iso: string): number {
    const target = new Date(iso);
    const now = new Date();
    return Math.ceil((target.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
}

function statusColour(days: number): string {
    if (days < 0) return 'text-red-600 dark:text-red-400';
    if (days <= 30) return 'text-amber-600 dark:text-amber-400';
    return 'text-emerald-600 dark:text-emerald-400';
}

function statusLabel(days: number): string {
    if (days < 0) return `Expired ${-days}d ago`;
    if (days === 0) return 'Expires today';
    return `${days}d remaining`;
}

export default function VehicleShow({ vehicle, compliance, complianceHistory, dvlaEnabled }: Props) {
    const [tab, setTab] = useState<'details' | 'compliance'>('details');

    return (
        <GarageLayout title={`Vehicle ${vehicle.registration}`}>
            <Head title={`Vehicle ${vehicle.registration}`} />
            <div className="flex justify-end mb-4">
                <Button asChild size="sm" variant="outline">
                    <Link href={`/vehicles/${vehicle.id}/edit`}>
                        <Pencil className="h-4 w-4" /> Edit
                    </Link>
                </Button>
            </div>

            <div className="max-w-lg">
                <div className="flex gap-1 border-b border-gray-200 dark:border-slate-800 mb-4" role="tablist">
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
                                    ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200')
                            }
                        >
                            {t === 'details' ? 'Details' : 'Compliance'}
                        </button>
                    ))}
                </div>

                {tab === 'details' && (
                    <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-6 space-y-4">
                        <div>
                            <p className={labelClass}>Registration</p>
                            <p className={valueClass}>{vehicle.registration}</p>
                        </div>
                        <div>
                            <p className={labelClass}>VIN</p>
                            <p className={valueClass + ' font-mono'}>{vehicle.vin ?? '—'}</p>
                        </div>
                        <div>
                            <p className={labelClass}>Make &amp; Model</p>
                            <p className={valueClass}>{vehicle.make} {vehicle.model}</p>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className={labelClass}>Year</p>
                                <p className={valueClass}>{vehicle.year ?? '—'}</p>
                            </div>
                            <div>
                                <p className={labelClass}>Colour</p>
                                <p className={valueClass}>{vehicle.colour ?? '—'}</p>
                            </div>
                        </div>
                        <div>
                            <p className={labelClass}>CRM Customer ID</p>
                            <p className={valueClass + ' font-mono'}>{vehicle.crm_customer_id}</p>
                        </div>
                        <div>
                            <p className={labelClass}>Added</p>
                            <p className={valueClass}>{formatDate(vehicle.created_at)}</p>
                        </div>
                    </div>
                )}

                {tab === 'compliance' && (
                    <ComplianceTab
                        vehicleId={vehicle.id}
                        compliance={compliance}
                        history={complianceHistory}
                        dvlaEnabled={dvlaEnabled}
                    />
                )}
            </div>
        </GarageLayout>
    );
}

interface ComplianceTabProps {
    vehicleId: string;
    compliance: Record<ComplianceType, ComplianceRecord | null>;
    history: ComplianceRecord[];
    dvlaEnabled: boolean;
}

function ComplianceTab({ vehicleId, compliance, history, dvlaEnabled }: ComplianceTabProps) {
    const [refreshing, setRefreshing] = useState(false);

    function refresh() {
        if (refreshing) return;
        setRefreshing(true);
        router.post(
            `/vehicles/${vehicleId}/compliance/refresh`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setRefreshing(false),
            },
        );
    }

    return (
        <div className="space-y-3">
            {dvlaEnabled && (
                <div className="flex items-center justify-between bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900/60 rounded-lg px-4 py-3">
                    <div>
                        <p className="text-sm font-medium text-blue-900 dark:text-blue-200">DVLA auto-fill</p>
                        <p className="text-xs text-blue-700 dark:text-blue-300">Pulls latest MOT &amp; Road Tax expiry for this registration.</p>
                    </div>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={refresh}
                        disabled={refreshing}
                    >
                        <RefreshCw className={'h-4 w-4 ' + (refreshing ? 'animate-spin' : '')} />
                        {refreshing ? 'Refreshing…' : 'Refresh'}
                    </Button>
                </div>
            )}
            {(['mot', 'tax', 'insurance'] as const).map((type) => (
                <ComplianceCard
                    key={type}
                    type={type}
                    record={compliance[type]}
                    vehicleId={vehicleId}
                />
            ))}
            {history.length > 0 && <HistoryList history={history} />}
        </div>
    );
}

interface ComplianceCardProps {
    type: ComplianceType;
    record: ComplianceRecord | null;
    vehicleId: string;
}

function ComplianceCard({ type, record, vehicleId }: ComplianceCardProps) {
    const [editing, setEditing] = useState(false);

    return (
        <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-4">
            <div className="flex items-center justify-between mb-2">
                <p className="text-sm font-semibold text-gray-900 dark:text-white">{COMPLIANCE_LABELS[type]}</p>
                {!editing && (
                    <Button size="sm" variant="outline" onClick={() => setEditing(true)}>
                        {record ? 'Update' : 'Set date'}
                    </Button>
                )}
            </div>

            {!editing && record && (
                <div className="space-y-1">
                    <p className="text-lg text-gray-900 dark:text-white">{formatDate(record.expires_on)}</p>
                    <p className={'text-xs font-medium ' + statusColour(daysUntil(record.expires_on))}>
                        {statusLabel(daysUntil(record.expires_on))}
                    </p>
                    {record.note && <p className="text-xs text-gray-500 dark:text-slate-400 italic mt-1">{record.note}</p>}
                </div>
            )}

            {!editing && !record && (
                <p className="text-sm text-gray-400 dark:text-slate-500">No date recorded yet.</p>
            )}

            {editing && (
                <ComplianceForm
                    type={type}
                    vehicleId={vehicleId}
                    defaultDate={record?.expires_on ?? ''}
                    onCancel={() => setEditing(false)}
                    onSaved={() => setEditing(false)}
                />
            )}
        </div>
    );
}

interface ComplianceFormProps {
    type: ComplianceType;
    vehicleId: string;
    defaultDate: string;
    onCancel: () => void;
    onSaved: () => void;
}

function ComplianceForm({ type, vehicleId, defaultDate, onCancel, onSaved }: ComplianceFormProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        type,
        expires_on: defaultDate,
        note: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(`/vehicles/${vehicleId}/compliance`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onSaved();
            },
        });
    }

    return (
        <form onSubmit={submit} className="space-y-3 mt-2">
            <div>
                <label htmlFor={`${type}-expires`} className="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1">
                    Expires on
                </label>
                <input
                    id={`${type}-expires`}
                    type="date"
                    className="text-sm border border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400"
                    value={data.expires_on}
                    onChange={(e) => setData('expires_on', e.target.value)}
                    required
                />
                {errors.expires_on && <p className="text-xs text-red-600 dark:text-red-400 mt-1">{errors.expires_on}</p>}
            </div>
            <div>
                <label htmlFor={`${type}-note`} className="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1">
                    Note <span className="text-gray-400 dark:text-slate-500">(optional)</span>
                </label>
                <input
                    id={`${type}-note`}
                    type="text"
                    maxLength={500}
                    className="text-sm border border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400"
                    value={data.note}
                    onChange={(e) => setData('note', e.target.value)}
                />
                {errors.note && <p className="text-xs text-red-600 dark:text-red-400 mt-1">{errors.note}</p>}
            </div>
            <div className="flex gap-2 justify-end">
                <Button type="button" size="sm" variant="outline" onClick={onCancel} disabled={processing}>
                    Cancel
                </Button>
                <Button type="submit" size="sm" disabled={processing}>
                    {processing ? 'Saving…' : 'Save'}
                </Button>
            </div>
        </form>
    );
}

function HistoryList({ history }: { history: ComplianceRecord[] }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="w-full px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800/40"
            >
                {open ? '▾' : '▸'} History ({history.length})
            </button>
            {open && (
                <ul className="divide-y divide-gray-100 dark:divide-slate-800 border-t border-gray-200 dark:border-slate-800">
                    {history.map((r) => (
                        <li key={r.id} className="px-4 py-2 text-xs text-gray-600 dark:text-slate-400 flex justify-between">
                            <span>
                                <span className="font-medium text-gray-900 dark:text-white">{COMPLIANCE_LABELS[r.type]}</span>
                                {' · '}
                                {formatDate(r.expires_on)}
                                {r.source !== 'manual' && (
                                    <span className="ml-2 px-1.5 py-0.5 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 rounded uppercase">
                                        {r.source}
                                    </span>
                                )}
                            </span>
                            <span className="text-gray-400 dark:text-slate-500">
                                {formatDate(r.created_at)}
                                {r.recorded_by && ` · ${r.recorded_by.name}`}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
