import { Head, Link } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { Plus } from 'lucide-react';

interface Vehicle { id: string; registration: string; make: string; model: string; year: number | null; colour: string | null }
interface Props { vehicles: Vehicle[] }

export default function VehiclesIndex({ vehicles }: Props) {
    return (
        <GarageLayout title="Vehicles">
            <Head title="Vehicles" />
            <div className="flex justify-end mb-4">
                <Button asChild size="sm">
                    <Link href="/vehicles/create"><Plus className="h-4 w-4" /> Add Vehicle</Link>
                </Button>
            </div>
            <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 overflow-hidden">
                {vehicles.length === 0 ? (
                    <div className="py-12 text-center text-sm text-gray-500 dark:text-slate-400">No vehicles yet.</div>
                ) : (
                    <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[560px]">
                        <thead className="bg-gray-50 dark:bg-slate-800/40 border-b border-gray-200 dark:border-slate-800">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">Registration</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">Make / Model</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 dark:text-slate-400">Year</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-slate-800">
                            {vehicles.map((v) => (
                                <tr key={v.id} className="hover:bg-gray-50 dark:hover:bg-slate-800/40">
                                    <td className="px-4 py-3 font-medium">
                                        <Link href={`/vehicles/${v.id}`} className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">{v.registration}</Link>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-slate-300">{v.make} {v.model}</td>
                                    <td className="px-4 py-3 text-gray-500 dark:text-slate-400">{v.year ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/vehicles/${v.id}/edit`} className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-xs font-medium">Edit</Link>
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
