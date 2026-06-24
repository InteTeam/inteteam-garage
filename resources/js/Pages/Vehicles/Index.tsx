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
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                {vehicles.length === 0 ? (
                    <div className="py-12 text-center text-sm text-gray-500">No vehicles yet.</div>
                ) : (
                    <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[560px]">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Registration</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Make / Model</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Year</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {vehicles.map((v) => (
                                <tr key={v.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">
                                        <Link href={`/vehicles/${v.id}`} className="text-blue-600 hover:text-blue-800">{v.registration}</Link>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{v.make} {v.model}</td>
                                    <td className="px-4 py-3 text-gray-500">{v.year ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/vehicles/${v.id}/edit`} className="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</Link>
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
