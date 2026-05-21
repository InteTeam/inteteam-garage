import { Head, Link } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';

interface Estimate { id: string; revision_number: number; sent_at: string | null; job_id: string }
interface Props { estimates: Estimate[] }

export default function EstimatesIndex({ estimates }: Props) {
    return (
        <GarageLayout title="Estimates">
            <Head title="Estimates" />
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                {estimates.length === 0 ? (
                    <div className="py-12 text-center text-sm text-gray-500">No estimates yet.</div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Revision</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Sent</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {estimates.map((e) => (
                                <tr key={e.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium text-gray-900">#{e.revision_number}</td>
                                    <td className="px-4 py-3 text-gray-500">
                                        {e.sent_at ? new Date(e.sent_at).toLocaleDateString('en-GB') : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/jobs/${e.job_id}`} className="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                            View Job →
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
