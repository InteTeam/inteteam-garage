import { Head, Link, router } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';
import { JobStateBadge } from '@/Components/JobStateBadge';
import { Button } from '@/Components/ui/button';
import { CheckCircle, XCircle, MessageCircle } from 'lucide-react';
import { useState } from 'react';

interface LineItem {
    id: string;
    description: string;
    // Laravel's `decimal:2` cast serialises as a string ("324.00").
    price: number | string;
    status: 'pending' | 'approved' | 'declined';
}

interface Estimate {
    id: string;
    revision_number: number;
    sent_at: string | null;
    line_items: LineItem[];
}

interface Job {
    id: string;
    state: string;
    garage: { name: string; online_payment_enabled: boolean };
    vehicle: { registration: string; make: string; model: string };
    current_estimate: Estimate | null;
    notification_preference: { channel: string } | null;
}

interface Props {
    job: Job;
    token: string;
}

export default function PortalJob({ job, token }: Props) {
    const [declining, setDeclining] = useState<string | null>(null);
    const [declineNote, setDeclineNote] = useState('');

    function approve(lineItemId: string) {
        router.post(`/portal/${token}/line-items/${lineItemId}/approve`);
    }

    function submitDecline(lineItemId: string) {
        if (!declineNote.trim()) return;
        router.post(`/portal/${token}/line-items/${lineItemId}/decline`, { notes: declineNote }, {
            onSuccess: () => { setDeclining(null); setDeclineNote(''); },
        });
    }

    const isPending = job.state === 'awaiting_approval';
    const lineItems = job.current_estimate?.line_items ?? [];
    const total = lineItems.reduce((sum, i) => sum + Number(i.price), 0);

    return (
        <PortalLayout title="Your Repair Job" garageName={job.garage.name}>
            <Head title="Your Repair Job" />

            <div className="mb-4 flex items-center gap-3">
                <span className="font-medium text-gray-700">
                    {job.vehicle.registration} · {job.vehicle.make} {job.vehicle.model}
                </span>
                <JobStateBadge state={job.state} />
            </div>

            {job.current_estimate && (
                <div className="bg-white rounded-lg border border-gray-200 p-5 mb-4">
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
                                        <span className={`text-xs font-semibold px-2 py-1 rounded-full ${
                                            item.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                        }`}>
                                            {item.status}
                                        </span>
                                    ) : isPending ? (
                                        <div className="flex gap-2 shrink-0">
                                            <button
                                                onClick={() => approve(item.id)}
                                                className="inline-flex items-center gap-1 text-xs font-medium text-green-700 hover:text-green-900"
                                            >
                                                <CheckCircle className="h-4 w-4" /> Approve
                                            </button>
                                            <button
                                                onClick={() => { setDeclining(item.id); setDeclineNote(''); }}
                                                className="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800"
                                            >
                                                <XCircle className="h-4 w-4" /> Decline
                                            </button>
                                        </div>
                                    ) : null}
                                </div>
                                {declining === item.id && (
                                    <div className="mt-3 space-y-2">
                                        <textarea
                                            className="w-full text-sm border border-gray-200 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                            rows={2}
                                            placeholder="Please explain why you're declining this item…"
                                            value={declineNote}
                                            onChange={(e) => setDeclineNote(e.target.value)}
                                        />
                                        <div className="flex gap-2">
                                            <Button size="sm" variant="destructive" onClick={() => submitDecline(item.id)}>
                                                Confirm Decline
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={() => setDeclining(null)}>
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 pt-4 border-t border-gray-100 flex justify-between text-sm">
                        <span className="font-medium text-gray-600">Total</span>
                        <span className="font-semibold text-gray-900">£{total.toFixed(2)}</span>
                    </div>
                </div>
            )}

            <div className="flex gap-3">
                <Button variant="outline" size="sm" asChild>
                    <Link href={`/portal/${token}/timeline`}>View Progress</Link>
                </Button>
                {job.garage.online_payment_enabled && job.state === 'awaiting_collection' && (
                    <Button size="sm" asChild>
                        <Link href={`/portal/${token}/payment`}>Pay Now</Link>
                    </Button>
                )}
            </div>
        </PortalLayout>
    );
}
