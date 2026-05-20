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
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                {jobs.length === 0 ? (
                    <div className="py-12 text-center text-sm text-gray-500">No jobs yet.</div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Vehicle</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">State</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Created</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {jobs.map((job) => (
                                <tr key={job.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium text-gray-900">
                                        {job.vehicle.registration}
                                        <span className="text-gray-500 font-normal ml-2">
                                            {job.vehicle.make} {job.vehicle.model}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3"><JobStateBadge state={job.state} /></td>
                                    <td className="px-4 py-3 text-gray-500">
                                        {new Date(job.created_at).toLocaleDateString('en-GB')}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/jobs/${job.id}`} className="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                            Open →
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </GarageLayout>
    );
}
