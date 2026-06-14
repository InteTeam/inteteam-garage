import { Head, Link } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { Pencil } from 'lucide-react';

interface Vehicle {
    id: string;
    crm_customer_id: string;
    registration: string;
    make: string;
    model: string;
    year: number | null;
    colour: string | null;
    created_at: string;
}

interface Props {
    vehicle: Vehicle;
}

const labelClass = 'text-xs uppercase tracking-wide text-gray-500 font-medium';
const valueClass = 'text-sm text-gray-900 mt-0.5';

export default function VehicleShow({ vehicle }: Props) {
    return (
        <GarageLayout title={`Vehicle ${vehicle.registration}`}>
            <Head title={`Vehicle ${vehicle.registration}`} />
            <div className="flex justify-end mb-4">
                <Button asChild size="sm" variant="outline">
                    <Link href={`/vehicles/${vehicle.id}/edit`}>
                        <Pencil className="h-4 w-4" /> Edit
                    </Link>
                </Button>
            </div>
            <div className="max-w-lg bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                <div>
                    <p className={labelClass}>Registration</p>
                    <p className={valueClass}>{vehicle.registration}</p>
                </div>
                <div>
                    <p className={labelClass}>Make &amp; Model</p>
                    <p className={valueClass}>{vehicle.make} {vehicle.model}</p>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <p className={labelClass}>Year</p>
                        <p className={valueClass}>{vehicle.year ?? '—'}</p>
                    </div>
                    <div>
                        <p className={labelClass}>Colour</p>
                        <p className={valueClass}>{vehicle.colour ?? '—'}</p>
                    </div>
                </div>
                <div>
                    <p className={labelClass}>CRM Customer ID</p>
                    <p className={valueClass + ' font-mono'}>{vehicle.crm_customer_id}</p>
                </div>
                <div>
                    <p className={labelClass}>Added</p>
                    <p className={valueClass}>{new Date(vehicle.created_at).toLocaleDateString('en-GB')}</p>
                </div>
            </div>
        </GarageLayout>
    );
}
