import { Head, useForm } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { FormEvent } from 'react';

interface Vehicle { id: string; registration: string; make: string; model: string }
interface Props { vehicles?: Vehicle[] }

const field = 'text-sm border border-gray-300 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500';
const label = 'block text-sm font-medium text-gray-700 mb-1';
const err = 'text-xs text-red-600 mt-1';

export default function JobCreate({ vehicles = [] }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        vehicle_id: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(route('jobs.store'));
    }

    return (
        <GarageLayout title="New Job">
            <Head title="New Job" />
            <div className="max-w-md bg-white rounded-lg border border-gray-200 p-6">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label htmlFor="vehicle_id" className={label}>Vehicle</label>
                        <select id="vehicle_id" className={field} value={data.vehicle_id} onChange={(e) => setData('vehicle_id', e.target.value)}>
                            <option value="">Select a vehicle…</option>
                            {vehicles.map((v) => (
                                <option key={v.id} value={v.id}>{v.registration} — {v.make} {v.model}</option>
                            ))}
                        </select>
                        {errors.vehicle_id && <p className={err}>{errors.vehicle_id}</p>}
                    </div>
                    <div className="flex justify-end pt-2">
                        <Button type="submit" disabled={processing}>{processing ? 'Creating…' : 'Create Job'}</Button>
                    </div>
                </form>
            </div>
        </GarageLayout>
    );
}
