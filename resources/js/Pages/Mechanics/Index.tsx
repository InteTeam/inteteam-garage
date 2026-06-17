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
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                {mechanics.length === 0 ? (
                    <div className="py-12 text-center text-sm text-gray-500">No mechanics yet.</div>
                ) : (
                    <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[560px]">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Role</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {mechanics.map((m) => (
                                <tr key={m.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{m.user.name}</p>
                                        <p className="text-xs text-gray-400">{m.user.email}</p>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{m.role}</td>
                                    <td className="px-4 py-3">
                                        <span className={`text-xs font-medium ${m.is_active ? 'text-green-600' : 'text-gray-400'}`}>
                                            {m.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/mechanics/${m.id}/edit`} className="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</Link>
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
