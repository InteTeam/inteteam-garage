import { Head, Link } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { JobStateBadge } from '@/Components/JobStateBadge';
import { Button } from '@/Components/ui/button';
import { Plus } from 'lucide-react';

interface Vehicle {
    registration: string;
    make: string;
    model: string;
}

interface Mechanic {
    id: string;
    user: { name: string };
}

interface Job {
    id: string;
    state: string;
    vehicle: Vehicle;
    mechanics: Mechanic[];
    created_at: string;
}

interface Props {
    activeJobs: Job[];
}

export default function Dashboard({ activeJobs }: Props) {
    return (
        <GarageLayout title="Dashboard">
            <Head title="Dashboard" />
            <div className="flex items-center justify-between mb-6">
                <p className="text-sm text-gray-500">{activeJobs.length} active job{activeJobs.length !== 1 ? 's' : ''}</p>
                <Button asChild size="sm">
                    <Link href="/jobs/create">
                        <Plus className="h-4 w-4" />
                        New Job
                    </Link>
                </Button>
            </div>
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                {activeJobs.length === 0 ? (
                    <div className="py-12 text-center text-sm text-gray-500">
                        No active jobs. Create one to get started.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[640px]">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Vehicle</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">State</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Assigned</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Created</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {activeJobs.map((job) => (
                                <tr key={job.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-4 py-3 font-medium text-gray-900">
                                        {job.vehicle.registration}
                                        <span className="text-gray-500 font-normal ml-2">
                                            {job.vehicle.make} {job.vehicle.model}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <JobStateBadge state={job.state} />
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">
                                        {job.mechanics.length > 0
                                            ? job.mechanics.map((m) => m.user.name).join(', ')
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">
                                        {new Date(job.created_at).toLocaleDateString('en-GB')}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={`/jobs/${job.id}`}
                                            className="text-blue-600 hover:text-blue-800 font-medium text-xs"
                                        >
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
