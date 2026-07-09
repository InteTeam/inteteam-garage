import { Head, Link } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { JobStateBadge } from '@/Components/JobStateBadge';
import { Button } from '@/Components/ui/button';
import { Plus } from 'lucide-react';

interface Vehicle { registration: string; make: string; model: string }
interface Job { id: string; state: string; vehicle: Vehicle; created_at: string }

interface Props { jobs: Job[] }

export default function JobsIndex({ jobs }: Props) {
    return (
        <GarageLayout title="Jobs">
            <Head title="Jobs" />
            <div className="flex justify-end mb-4">
                <Button asChild size="sm">
                    <Link href="/jobs/create"><Plus className="h-4 w-4" /> New Job</Link>
                </Button>
            </div>
            <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 overflow-hidden">
                {jobs.length === 0 ? (
                    <div className="py-12 text-center text-sm text-gray-500 dark:text-slate-400">No jobs yet.</div>
                ) : (
                    <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[560px]">
                        <thead className="bg-gray-50 dark:bg-slate-800/40 border-b border-gray-200 dark:border-slate-800">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">Vehicle</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">State</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">Created</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-slate-800">
                            {jobs.map((job) => (
                                <tr key={job.id} className="hover:bg-gray-50 dark:hover:bg-slate-800/40">
                                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                        {job.vehicle.registration}
                                        <span className="text-gray-500 dark:text-slate-400 font-normal ml-2">
                                            {job.vehicle.make} {job.vehicle.model}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3"><JobStateBadge state={job.state} /></td>
                                    <td className="px-4 py-3 text-gray-500 dark:text-slate-400">
                                        {new Date(job.created_at).toLocaleDateString('en-GB')}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/jobs/${job.id}`} className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-xs font-medium">
                                            Open →
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>
                )}
            </div>
        </GarageLayout>
    );
}
