import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, MessageCircle, XCircle } from 'lucide-react';
import { useState } from 'react';
import CustomerLayout from '@/Layouts/CustomerLayout';
import { JobStateBadge } from '@/Components/JobStateBadge';
import { Button } from '@/Components/ui/button';

interface LineItem {
    id: string;
    description: string;
    // Laravel `decimal:2` serialises as string ("324.00") — same gotcha as portal token view.
    price: number | string;
    status: 'pending' | 'approved' | 'declined';
}

interface Estimate {
    id: string;
    revision_number: number;
    sent_at: string | null;
    line_items: LineItem[];
}

interface Stage {
    id: string;
    name: string;
    notes: string | null;
    media: Array<{ id: string; url: string | null; mime_type: string }>;
}

interface Transition {
    id: string;
    from_state: string | null;
    to_state: string;
    created_at: string;
}

interface Job {
    id: string;
    state: string;
    garage: { id: string; name: string; online_payment_enabled: boolean };
    vehicle: { id: string; registration: string; make: string; model: string; year: number | null };
    current_estimate: Estimate | null;
    stages: Stage[];
    state_transitions: Transition[];
    notification_preference: { channel: string } | null;
}

interface Props {
    job: Job;
}

export default function CustomerJobShow({ job }: Props) {
    const [declining, setDeclining] = useState<string | null>(null);
    const [questioning, setQuestioning] = useState<string | null>(null);
    const [note, setNote] = useState('');

    function approve(lineItemId: string) {
        router.post(`/account/jobs/${job.id}/line-items/${lineItemId}/approve`);
    }

    function decline(lineItemId: string) {
        if (!note.trim()) return;
        router.post(
            `/account/jobs/${job.id}/line-items/${lineItemId}/decline`,
            { notes: note },
            { onSuccess: () => { setDeclining(null); setNote(''); } },
        );
    }

    function question(lineItemId: string) {
        if (!note.trim()) return;
        // Field is `message` (signed-portal parity); `notes` is for decline only.
        router.post(
            `/account/jobs/${job.id}/line-items/${lineItemId}/question`,
            { message: note },
            { onSuccess: () => { setQuestioning(null); setNote(''); } },
        );
    }

    const isPending = job.state === 'awaiting_approval' || job.state === 'customer_query';
    const lineItems = job.current_estimate?.line_items ?? [];
    const total = lineItems.reduce((sum, i) => sum + Number(i.price), 0);

    return (
        <CustomerLayout title={`Job · ${job.vehicle.registration}`}>
            <Head title={`Job · ${job.vehicle.registration}`} />

            <div className="mb-4">
                <Link href="/account" className="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-3.5 w-3.5" /> Back
                </Link>
            </div>

            <div className="mb-5 flex flex-wrap items-center gap-3">
                <span className="font-medium text-gray-900">
                    {job.vehicle.registration} · {job.vehicle.make} {job.vehicle.model}
                </span>
                <JobStateBadge state={job.state} />
                <span className="text-xs text-gray-500 ml-auto">{job.garage.name}</span>
            </div>

            {job.current_estimate && (
                <section className="bg-white rounded-lg border border-gray-200 p-5 mb-6">
                    <h2 className="font-semibold text-gray-900 mb-4">
                        Estimate #{job.current_estimate.revision_number}
                    </h2>
                    <div className="space-y-3">
                        {lineItems.map((item) => (
                            <div key={item.id} className="border border-gray-100 rounded-md p-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-gray-900">{item.description}</p>
                                        <p className="text-sm text-gray-500">£{Number(item.price).toFixed(2)}</p>
                                    </div>
                                    {item.status !== 'pending' ? (
                                        <span
                                            className={`text-xs font-semibold px-2 py-1 rounded-full ${
                                                item.status === 'approved'
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-red-100 text-red-700'
                                            }`}
                                        >
                                            {item.status}
                                        </span>
                                    ) : isPending ? (
                                        <div className="flex flex-wrap gap-2 shrink-0">
                                            <button
                                                onClick={() => approve(item.id)}
                                                className="inline-flex items-center gap-1 text-xs font-medium text-green-700 hover:text-green-900"
                                            >
                                                <CheckCircle className="h-4 w-4" /> Approve
                                            </button>
                                            <button
                                                onClick={() => { setDeclining(item.id); setQuestioning(null); setNote(''); }}
                                                className="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800"
                                            >
                                                <XCircle className="h-4 w-4" /> Decline
                                            </button>
                                            <button
                                                onClick={() => { setQuestioning(item.id); setDeclining(null); setNote(''); }}
                                                className="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800"
                                            >
                                                <MessageCircle className="h-4 w-4" /> Question
                                            </button>
                                        </div>
                                    ) : null}
                                </div>

                                {declining === item.id && (
                                    <NoteForm
                                        placeholder="Please explain why you're declining this item…"
                                        value={note}
                                        onChange={setNote}
                                        onSubmit={() => decline(item.id)}
                                        onCancel={() => { setDeclining(null); setNote(''); }}
                                        submitLabel="Confirm Decline"
                                        variant="destructive"
                                    />
                                )}

                                {questioning === item.id && (
                                    <NoteForm
                                        placeholder="What would you like to ask about this item?"
                                        value={note}
                                        onChange={setNote}
                                        onSubmit={() => question(item.id)}
                                        onCancel={() => { setQuestioning(null); setNote(''); }}
                                        submitLabel="Send Question"
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 pt-4 border-t border-gray-100 flex justify-between text-sm">
                        <span className="font-medium text-gray-600">Total</span>
                        <span className="font-semibold text-gray-900">£{total.toFixed(2)}</span>
                    </div>
                </section>
            )}

            {job.stages.length > 0 && (
                <section className="bg-white rounded-lg border border-gray-200 p-5 mb-6">
                    <h2 className="font-semibold text-gray-900 mb-4">Progress</h2>
                    <ol className="space-y-3">
                        {job.stages.map((stage) => (
                            <li key={stage.id} className="border-l-2 border-gray-200 pl-4 py-1">
                                <div className="flex items-center gap-2">
                                    <span className="font-medium text-gray-900 text-sm capitalize">{stage.name.replace(/-/g, ' ')}</span>
                                </div>
                                {stage.notes && <p className="text-xs text-gray-600 mt-1">{stage.notes}</p>}
                                {stage.media.length > 0 && (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {stage.media.map((m) =>
                                            m.url ? (
                                                <a
                                                    key={m.id}
                                                    href={m.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-block"
                                                >
                                                    {m.mime_type.startsWith('image/') ? (
                                                        <img
                                                            src={m.url}
                                                            alt=""
                                                            className="h-20 w-20 object-cover rounded border border-gray-200"
                                                        />
                                                    ) : (
                                                        <span className="text-xs text-blue-600 underline">View attachment</span>
                                                    )}
                                                </a>
                                            ) : null,
                                        )}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ol>
                </section>
            )}

            {job.state_transitions.length > 0 && (
                <details className="bg-white rounded-lg border border-gray-200 p-4">
                    <summary className="text-sm font-medium text-gray-700 cursor-pointer">
                        Timeline ({job.state_transitions.length})
                    </summary>
                    <ul className="mt-3 space-y-1.5 text-xs text-gray-600">
                        {job.state_transitions.map((t) => (
                            <li key={t.id} className="flex items-center gap-2">
                                <span className="text-gray-400">{new Date(t.created_at).toLocaleString()}</span>
                                <span>
                                    {t.from_state ? `${t.from_state.replace(/_/g, ' ')} → ` : ''}
                                    <span className="font-medium">{t.to_state.replace(/_/g, ' ')}</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </details>
            )}
        </CustomerLayout>
    );
}

interface NoteFormProps {
    placeholder: string;
    value: string;
    onChange: (v: string) => void;
    onSubmit: () => void;
    onCancel: () => void;
    submitLabel: string;
    variant?: 'destructive';
}

function NoteForm({ placeholder, value, onChange, onSubmit, onCancel, submitLabel, variant }: NoteFormProps) {
    return (
        <div className="mt-3 space-y-2">
            <textarea
                className="w-full text-sm border border-gray-200 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                rows={2}
                placeholder={placeholder}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            <div className="flex gap-2">
                <Button size="sm" variant={variant === 'destructive' ? 'destructive' : 'default'} onClick={onSubmit} disabled={!value.trim()}>
                    {submitLabel}
                </Button>
                <Button size="sm" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
            </div>
        </div>
    );
}
