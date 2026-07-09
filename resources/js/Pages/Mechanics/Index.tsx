import { Head, Link } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { Plus } from 'lucide-react';

interface Mechanic { id: string; role: string; is_active: boolean; user: { name: string; email: string } }
interface Props { mechanics: Mechanic[] }

export default function MechanicsIndex({ mechanics }: Props) {
    return (
        <GarageLayout title="Mechanics">
            <Head title="Mechanics" />
            <div className="flex justify-end mb-4">
                <Button asChild size="sm">
                    <Link href="/mechanics/create"><Plus className="h-4 w-4" /> Add Mechanic</Link>
                </Button>
            </div>
            <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 overflow-hidden">
                {mechanics.length === 0 ? (
                    <div className="py-12 text-center text-sm text-gray-500 dark:text-slate-400">No mechanics yet.</div>
                ) : (
                    <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[560px]">
                        <thead className="bg-gray-50 dark:bg-slate-800/40 border-b border-gray-200 dark:border-slate-800">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">Name</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">Role</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-slate-800">
                            {mechanics.map((m) => (
                                <tr key={m.id} className="hover:bg-gray-50 dark:hover:bg-slate-800/40">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900 dark:text-white">{m.user.name}</p>
                                        <p className="text-xs text-gray-400 dark:text-slate-500">{m.user.email}</p>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-slate-300">{m.role}</td>
                                    <td className="px-4 py-3">
                                        <span className={`text-xs font-medium ${m.is_active ? 'text-green-600 dark:text-emerald-400' : 'text-gray-400 dark:text-slate-500'}`}>
                                            {m.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/mechanics/${m.id}/edit`} className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-xs font-medium">Edit</Link>
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
