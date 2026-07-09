import { Head, Link } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';

interface Estimate { id: string; revision_number: number; sent_at: string | null; job_id: string }
interface Props { estimates: Estimate[] }

export default function EstimatesIndex({ estimates }: Props) {
    return (
        <GarageLayout title="Estimates">
            <Head title="Estimates" />
            <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 overflow-hidden">
                {estimates.length === 0 ? (
                    <div className="py-12 text-center text-sm text-gray-500 dark:text-slate-400">No estimates yet.</div>
                ) : (
                    <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[480px]">
                        <thead className="bg-gray-50 dark:bg-slate-800/40 border-b border-gray-200 dark:border-slate-800">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">Revision</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">Sent</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-slate-800">
                            {estimates.map((e) => (
                                <tr key={e.id} className="hover:bg-gray-50 dark:hover:bg-slate-800/40">
                                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">#{e.revision_number}</td>
                                    <td className="px-4 py-3 text-gray-500 dark:text-slate-400">
                                        {e.sent_at ? new Date(e.sent_at).toLocaleDateString('en-GB') : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/jobs/${e.job_id}`} className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-xs font-medium">
                                            View Job →
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
