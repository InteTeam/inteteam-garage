import { Head, router } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';
import { Button } from '@/Components/ui/button';
import { useState } from 'react';

// Laravel's `decimal:2` cast serialises price as a string ("324.00").
interface LineItem { id: string; description: string; price: number | string }
interface Estimate { line_items: LineItem[] }
interface HandoverInspection { submitted_at: string }
interface Job {
    id: string;
    garage: { name: string };
    current_estimate: Estimate | null;
    handover_inspection: HandoverInspection | null;
}

interface Props { job: Job; token: string }

export default function PortalHandover({ job, token }: Props) {
    const lineItems = job.current_estimate?.line_items ?? [];
    const [items, setItems] = useState<Record<string, { accepted: boolean; notes: string }>>(
        Object.fromEntries(lineItems.map((i) => [i.id, { accepted: true, notes: '' }]))
    );

    if (job.handover_inspection) {
        return (
            <PortalLayout title="Handover Complete" garageName={job.garage.name}>
                <Head title="Handover" />
                <p className="text-sm text-gray-600">
                    Handover submitted on {new Date(job.handover_inspection.submitted_at).toLocaleString('en-GB')}.
                </p>
            </PortalLayout>
        );
    }

    function submit() {
        const payload = Object.entries(items).map(([line_item_id, v]) => ({
            line_item_id,
            accepted: v.accepted,
            notes: v.notes || null,
        }));

        if (payload.some((p) => !p.accepted && !p.notes)) {
            alert('Please add notes for any items you are not accepting.');
            return;
        }

        router.post(`/portal/${token}/handover`, { items: payload });
    }

    return (
        <PortalLayout title="Vehicle Handover" garageName={job.garage.name}>
            <Head title="Handover" />
            <p className="text-sm text-gray-600 mb-5">
                Please check each item and confirm whether you accept the completed work.
                Notes are required for any item you do not accept.
            </p>

            <div className="space-y-3 mb-6">
                {lineItems.map((item) => {
                    const state = items[item.id];
                    return (
                        <div key={item.id} className="bg-white border border-gray-200 rounded-lg p-4">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{item.description}</p>
                                    <p className="text-xs text-gray-500">£{Number(item.price).toFixed(2)}</p>
                                </div>
                                <div className="flex gap-3 shrink-0">
                                    <label className="flex items-center gap-1.5 text-sm cursor-pointer">
                                        <input
                                            type="radio"
                                            checked={state.accepted}
                                            onChange={() => setItems((p) => ({ ...p, [item.id]: { ...p[item.id], accepted: true } }))}
                                            className="accent-green-600"
                                        />
                                        <span className="text-green-700 font-medium">Accept</span>
                                    </label>
                                    <label className="flex items-center gap-1.5 text-sm cursor-pointer">
                                        <input
                                            type="radio"
                                            checked={!state.accepted}
                                            onChange={() => setItems((p) => ({ ...p, [item.id]: { ...p[item.id], accepted: false } }))}
                                            className="accent-red-600"
                                        />
                                        <span className="text-red-700 font-medium">Query</span>
                                    </label>
                                </div>
                            </div>
                            {!state.accepted && (
                                <textarea
                                    className="mt-3 w-full text-sm border border-gray-200 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    rows={2}
                                    placeholder="Describe the issue…"
                                    value={state.notes}
                                    onChange={(e) => setItems((p) => ({ ...p, [item.id]: { ...p[item.id], notes: e.target.value } }))}
                                />
                            )}
                        </div>
                    );
                })}
            </div>

            <Button onClick={submit} className="w-full">Submit Handover</Button>
        </PortalLayout>
    );
}
