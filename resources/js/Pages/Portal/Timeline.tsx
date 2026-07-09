import { Head, Link } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';
import { JobStateBadge } from '@/Components/JobStateBadge';
import { ArrowLeft } from 'lucide-react';

interface StateTransition { id: string; from_state: string; to_state: string; occurred_at: string }
interface Job { state: string; garage: { name: string }; state_transitions: StateTransition[] }
interface Props { job: Job; token: string }

export default function PortalTimeline({ job, token }: Props) {
    return (
        <PortalLayout title="Repair Progress" garageName={job.garage.name}>
            <Head title="Progress Timeline" />
            <Link href={`/portal/${token}`} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200 mb-6">
                <ArrowLeft className="h-3.5 w-3.5" />
                Back
            </Link>

            <div className="flex items-center gap-2 mb-6">
                <span className="text-sm text-gray-600 dark:text-slate-400">Current status:</span>
                <JobStateBadge state={job.state} />
            </div>

            <ol className="relative border-l border-gray-200 dark:border-slate-800 space-y-6 pl-5">
                {job.state_transitions.map((t) => (
                    <li key={t.id} className="relative">
                        <div className="absolute -left-[21px] mt-1 h-3 w-3 rounded-full bg-gray-400 dark:bg-slate-500 border-2 border-white dark:border-slate-950" />
                        <p className="text-sm font-medium text-gray-900 dark:text-white">
                            {t.to_state.replace(/_/g, ' ')}
                        </p>
                        <p className="text-xs text-gray-400 dark:text-slate-500">
                            {new Date(t.occurred_at).toLocaleString('en-GB')}
                        </p>
                    </li>
                ))}
            </ol>
        </PortalLayout>
    );
}
