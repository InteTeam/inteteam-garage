import { Head, Link, router } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { JobStateBadge } from '@/Components/JobStateBadge';
import { StageNotesEditor, JobStage } from '@/Components/StageNotesEditor';
import { TranslationPreviewDialog } from '@/Components/TranslationPreviewDialog';
import { Button } from '@/Components/ui/button';
import { AlertTriangle, ArrowLeft, ChevronRight } from 'lucide-react';

const VALID_TRANSITIONS: Record<string, string[]> = {
    created:             ['booked'],
    booked:              ['in_progress'],
    in_progress:         ['awaiting_approval'],
    awaiting_approval:   ['customer_query', 'approved'],
    customer_query:      ['awaiting_approval'],
    scope_change:        ['awaiting_approval', 'in_progress'],
    approved:            ['completed', 'scope_change'],
    completed:           ['awaiting_collection'],
    awaiting_collection: ['collected'],
};

const STATE_LABELS: Record<string, string> = {
    booked: 'Mark Booked',
    in_progress: 'Start Work',
    awaiting_approval: 'Send Estimate',
    customer_query: 'Flag Customer Query',
    approved: 'Mark Approved',
    scope_change: 'Raise Scope Change',
    completed: 'Mark Completed',
    awaiting_collection: 'Ready for Collection',
    collected: 'Mark Collected',
};

interface Vehicle { registration: string; make: string; model: string; }
interface Mechanic { id: string; user: { name: string } }
interface LineItem { id: string; description: string; price: number; status: string }
interface Estimate { id: string; revision_number: number; sent_at: string | null; preview_confirmed_at: string | null; line_items: LineItem[] }
interface StateTransition { id: string; from_state: string; to_state: string; occurred_at: string }
interface HandoverItem { id: string; accepted: boolean; notes: string | null; line_item: { description: string } }
interface HandoverInspection { id: string; submitted_at: string; items: HandoverItem[] }

interface Job {
    id: string;
    state: string;
    vehicle: Vehicle;
    mechanics: Mechanic[];
    current_estimate: Estimate | null;
    state_transitions: StateTransition[];
    handover_inspection: HandoverInspection | null;
    stages: JobStage[];
    created_at: string;
}

interface Props {
    job: Job;
}

export default function JobShow({ job }: Props) {
    const allowedNext = VALID_TRANSITIONS[job.state] ?? [];
    const flaggedHandoverItems = job.handover_inspection?.items.filter(
        (item) => !item.accepted || (item.notes !== null && item.notes.trim() !== ''),
    ) ?? [];

    function transition(toState: string) {
        router.post(`/jobs/${job.id}/transition`, { state: toState });
    }

    return (
        <GarageLayout>
            <Head title={`Job — ${job.vehicle.registration}`} />

            <div className="mb-4">
                <Link href="/jobs" className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-3.5 w-3.5" />
                    Back to Jobs
                </Link>
            </div>

            <div className="flex items-start justify-between mb-6">
                <div>
                    <h1 className="text-lg font-semibold text-gray-900">
                        {job.vehicle.registration}
                        <span className="text-gray-500 font-normal ml-2 text-base">
                            {job.vehicle.make} {job.vehicle.model}
                        </span>
                    </h1>
                    <div className="mt-1">
                        <JobStateBadge state={job.state} />
                    </div>
                </div>
                <div className="flex gap-2">
                    {allowedNext.map((next) => (
                        <Button
                            key={next}
                            size="sm"
                            variant={next === 'collected' ? 'success' : next === 'scope_change' ? 'outline' : 'default'}
                            onClick={() => transition(next)}
                        >
                            <ChevronRight className="h-3.5 w-3.5" />
                            {STATE_LABELS[next] ?? next}
                        </Button>
                    ))}
                </div>
            </div>

            {job.handover_inspection && flaggedHandoverItems.length > 0 && (
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <div className="flex items-center gap-2 text-sm font-medium text-amber-900">
                        <AlertTriangle className="h-4 w-4" />
                        Handover follow-up needed ({flaggedHandoverItems.length})
                    </div>
                    <ul className="mt-2 space-y-1 text-sm text-amber-900">
                        {flaggedHandoverItems.map((item) => (
                            <li key={item.id} className="flex items-start gap-2">
                                <span className="font-medium">{item.line_item.description}</span>
                                {!item.accepted && <span className="text-amber-700">— not accepted</span>}
                                {item.notes && <span className="text-amber-800">: {item.notes}</span>}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="grid grid-cols-3 gap-4">
                <div className="col-span-2 space-y-4">
                    <StageNotesEditor jobId={job.id} stages={job.stages} />
                    {job.current_estimate && (
                        <div className="bg-white rounded-lg border border-gray-200 p-4">
                            <div className="flex items-center justify-between mb-3">
                                <h2 className="font-medium text-gray-900 text-sm">
                                    Estimate #{job.current_estimate.revision_number}
                                </h2>
                                {!job.current_estimate.sent_at && (
                                    job.current_estimate.preview_confirmed_at ? (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.post(`/jobs/${job.id}/estimates/${job.current_estimate!.id}/send`)}
                                        >
                                            Send to Customer
                                        </Button>
                                    ) : (
                                        <TranslationPreviewDialog
                                            jobId={job.id}
                                            estimateId={job.current_estimate.id}
                                            trigger={
                                                <Button size="sm" variant="outline">
                                                    Preview &amp; Send
                                                </Button>
                                            }
                                        />
                                    )
                                )}
                            </div>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100">
                                        <th className="pb-2 text-left font-medium text-gray-600">Description</th>
                                        <th className="pb-2 text-right font-medium text-gray-600">Price</th>
                                        <th className="pb-2 text-right font-medium text-gray-600">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {job.current_estimate.line_items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="py-2 text-gray-800">{item.description}</td>
                                            <td className="py-2 text-right text-gray-800">£{item.price.toFixed(2)}</td>
                                            <td className="py-2 text-right">
                                                <span className={`text-xs font-medium ${
                                                    item.status === 'approved' ? 'text-green-600' :
                                                    item.status === 'declined' ? 'text-red-600' : 'text-gray-500'
                                                }`}>
                                                    {item.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colSpan={2} className="pt-3 text-right text-sm font-semibold text-gray-900">
                                            Total: £{job.current_estimate.line_items.reduce((s, i) => s + i.price, 0).toFixed(2)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </div>

                <div className="space-y-4">
                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 className="font-medium text-gray-900 text-sm mb-3">Assigned Mechanics</h2>
                        {job.mechanics.length === 0 ? (
                            <p className="text-sm text-gray-400">None assigned</p>
                        ) : (
                            <ul className="space-y-1">
                                {job.mechanics.map((m) => (
                                    <li key={m.id} className="text-sm text-gray-700">{m.user.name}</li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 className="font-medium text-gray-900 text-sm mb-3">State History</h2>
                        <ul className="space-y-2">
                            {job.state_transitions.map((t) => (
                                <li key={t.id} className="text-xs text-gray-500">
                                    <span className="font-medium text-gray-700">{t.from_state}</span>
                                    {' → '}
                                    <span className="font-medium text-gray-700">{t.to_state}</span>
                                    <div>{new Date(t.occurred_at).toLocaleString('en-GB')}</div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 className="font-medium text-gray-900 text-sm mb-2">Portal Link</h2>
                        <Button
                            size="sm"
                            variant="outline"
                            asChild
                        >
                            <Link href={`/jobs/${job.id}/portal-link`}>Manage Portal Link</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </GarageLayout>
    );
}
