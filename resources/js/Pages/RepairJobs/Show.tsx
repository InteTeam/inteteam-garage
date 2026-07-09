import { Head, Link, router, usePage } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { JobStateBadge } from '@/Components/JobStateBadge';
import { StageNotesEditor, JobStage } from '@/Components/StageNotesEditor';
import { EstimatePanel, Estimate } from '@/Components/EstimatePanel';
import { JobSidebar } from '@/Components/JobSidebar';
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
    stagesAvailableToAdd: string[];
}

export default function JobShow({ job, stagesAvailableToAdd }: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const allowedNext = VALID_TRANSITIONS[job.state] ?? [];
    const flaggedHandoverItems = job.handover_inspection?.items.filter(
        (item) => !item.accepted || (item.notes !== null && item.notes.trim() !== ''),
    ) ?? [];

    // Poka-Yoke mirror of JobStateMachine guards (planning.md L99/L101/L103).
    // Backend remains authoritative; this just stops the mechanic from clicking
    // a button that will guaranteed-fail and surfaces *why* before they try.
    function guardReason(next: string): string | null {
        const lineItems = job.current_estimate?.line_items ?? [];
        if (next === 'awaiting_approval' && lineItems.length === 0) {
            return 'Add at least one line item to the estimate first.';
        }
        if (next === 'completed' && (lineItems.length === 0 || lineItems.some((i) => i.status === 'pending'))) {
            return 'All line items must be approved or declined first.';
        }
        if (next === 'collected' && job.handover_inspection === null) {
            return 'The customer has not submitted the handover inspection yet.';
        }
        return null;
    }

    function transition(toState: string) {
        router.post(`/jobs/${job.id}/transition`, { state: toState });
    }

    return (
        <GarageLayout>
            <Head title={`Job — ${job.vehicle.registration}`} />

            <div className="mb-4">
                <Link href="/jobs" className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">
                    <ArrowLeft className="h-3.5 w-3.5" />
                    Back to Jobs
                </Link>
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
                <div>
                    <h1 className="text-lg font-semibold text-gray-900 dark:text-white">
                        {job.vehicle.registration}
                        <span className="text-gray-500 dark:text-slate-400 font-normal ml-2 text-base">
                            {job.vehicle.make} {job.vehicle.model}
                        </span>
                    </h1>
                    <div className="mt-1">
                        <JobStateBadge state={job.state} />
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {allowedNext.map((next) => {
                        const blockedReason = guardReason(next);
                        return (
                            <Button
                                key={next}
                                size="sm"
                                variant={next === 'collected' ? 'success' : next === 'scope_change' ? 'outline' : 'default'}
                                onClick={() => transition(next)}
                                disabled={blockedReason !== null}
                                title={blockedReason ?? undefined}
                            >
                                <ChevronRight className="h-3.5 w-3.5" />
                                {STATE_LABELS[next] ?? next}
                            </Button>
                        );
                    })}
                </div>
            </div>

            {errors.transition && (
                <div className="mb-4 rounded-lg border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 p-3 text-sm text-red-800 dark:text-red-300">
                    {errors.transition}
                </div>
            )}

            {job.handover_inspection && flaggedHandoverItems.length > 0 && (
                <div className="mb-4 rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 p-4">
                    <div className="flex items-center gap-2 text-sm font-medium text-amber-900 dark:text-amber-200">
                        <AlertTriangle className="h-4 w-4" />
                        Handover follow-up needed ({flaggedHandoverItems.length})
                    </div>
                    <ul className="mt-2 space-y-1 text-sm text-amber-900 dark:text-amber-200">
                        {flaggedHandoverItems.map((item) => (
                            <li key={item.id} className="flex items-start gap-2">
                                <span className="font-medium">{item.line_item.description}</span>
                                {!item.accepted && <span className="text-amber-700 dark:text-amber-400">— not accepted</span>}
                                {item.notes && <span className="text-amber-800 dark:text-amber-300">: {item.notes}</span>}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="md:col-span-2 space-y-4">
                    <StageNotesEditor
                        jobId={job.id}
                        stages={job.stages}
                        availableToAdd={stagesAvailableToAdd}
                    />
                    {job.current_estimate ? (
                        <EstimatePanel jobId={job.id} estimate={job.current_estimate} />
                    ) : (
                        <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-4 flex items-center justify-between">
                            <div>
                                <h2 className="font-medium text-gray-900 dark:text-white text-sm">No estimate yet</h2>
                                <p className="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Create one to start adding line items for the customer.</p>
                            </div>
                            <Button
                                size="sm"
                                onClick={() => router.post(`/jobs/${job.id}/estimates`)}
                            >
                                Create Estimate
                            </Button>
                        </div>
                    )}
                </div>
                <JobSidebar
                    jobId={job.id}
                    mechanics={job.mechanics}
                    stateTransitions={job.state_transitions}
                />
            </div>
        </GarageLayout>
    );
}
