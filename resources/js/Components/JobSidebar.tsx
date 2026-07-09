import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';

interface Mechanic { id: string; user: { name: string } }
interface StateTransition { id: string; from_state: string; to_state: string; occurred_at: string }
interface Props {
    jobId: string;
    mechanics: Mechanic[];
    stateTransitions: StateTransition[];
}

export function JobSidebar({ jobId, mechanics, stateTransitions }: Props) {
    return (
        <div className="space-y-4">
            <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-4">
                <h2 className="font-medium text-gray-900 dark:text-white text-sm mb-3">Assigned Mechanics</h2>
                {mechanics.length === 0 ? (
                    <p className="text-sm text-gray-400 dark:text-slate-500">None assigned</p>
                ) : (
                    <ul className="space-y-1">
                        {mechanics.map((m) => (
                            <li key={m.id} className="text-sm text-gray-700 dark:text-slate-300">{m.user.name}</li>
                        ))}
                    </ul>
                )}
            </div>
            <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-4">
                <h2 className="font-medium text-gray-900 dark:text-white text-sm mb-3">State History</h2>
                <ul className="space-y-2">
                    {stateTransitions.map((t) => (
                        <li key={t.id} className="text-xs text-gray-500 dark:text-slate-400">
                            <span className="font-medium text-gray-700 dark:text-slate-300">{t.from_state}</span>
                            {' → '}
                            <span className="font-medium text-gray-700 dark:text-slate-300">{t.to_state}</span>
                            <div>{new Date(t.occurred_at).toLocaleString('en-GB')}</div>
                        </li>
                    ))}
                </ul>
            </div>
            <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-4">
                <h2 className="font-medium text-gray-900 dark:text-white text-sm mb-2">Portal Link</h2>
                <Button size="sm" variant="outline" asChild>
                    <Link href={`/jobs/${jobId}/portal-link`}>Manage Portal Link</Link>
                </Button>
            </div>
        </div>
    );
}
