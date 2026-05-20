import { Head, useForm } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { FormEvent } from 'react';

interface Vehicle {
    id: string;
    registration: string;
    make: string;
    model: string;
    year: string;
    colour: string;
}

interface Props {
    vehicle?: Vehicle | null;
    onSuccess?: () => void;
}

const field = 'text-sm border border-gray-300 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500';
const label = 'block text-sm font-medium text-gray-700 mb-1';
const err = 'text-xs text-red-600 mt-1';

export default function VehicleForm({ vehicle }: Props) {
    const { data, setData, post, put, processing, errors } = useForm({
        registration: vehicle?.registration ?? '',
        make: vehicle?.make ?? '',
        model: vehicle?.model ?? '',
        year: vehicle?.year ?? '',
        colour: vehicle?.colour ?? '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        if (vehicle) { put(route('vehicles.update', vehicle.id)); }
        else { post(route('vehicles.store')); }
    }

    return (
        <GarageLayout title={vehicle ? 'Edit Vehicle' : 'Add Vehicle'}>
            <Head title={vehicle ? 'Edit Vehicle' : 'Add Vehicle'} />
            <div className="max-w-lg bg-white rounded-lg border border-gray-200 p-6">
                <form onSubmit={submit} className="space-y-4">
                    {(['registration', 'make', 'model', 'colour'] as const).map((f) => (
                        <div key={f}>
                            <label htmlFor={f} className={label}>{f.charAt(0).toUpperCase() + f.slice(1)}</label>
                            <input id={f} type="text" className={field} value={data[f]} onChange={(e) => setData(f, e.target.value)} />
                            {errors[f] && <p className={err}>{errors[f]}</p>}
                        </div>
                    ))}
                    <div>
                        <label htmlFor="year" className={label}>Year</label>
                        <input id="year" type="number" className={field} value={data.year} onChange={(e) => setData('year', e.target.value)} />
                        {errors.year && <p className={err}>{errors.year}</p>}
                    </div>
                    <div className="flex justify-end pt-2">
                        <Button type="submit" disabled={processing}>{processing ? 'Saving…' : (vehicle ? 'Update' : 'Create')}</Button>
                    </div>
                </form>
            </div>
        </GarageLayout>
    );
}
