import { Head, useForm } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { FormEvent } from 'react';

interface Vehicle { id: string; registration: string; make: string; model: string }
interface Mechanic { id: string; name: string; role: string }
interface Props { vehicles?: Vehicle[]; mechanics?: Mechanic[] }

const field = 'text-sm border border-gray-300 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500';
const label = 'block text-sm font-medium text-gray-700 mb-1';
const err = 'text-xs text-red-600 mt-1';
const hint = 'text-xs text-gray-500 mt-1';

export default function JobCreate({ vehicles = [], mechanics = [] }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        vehicle_id: string;
        mechanic_ids: string[];
    }>({
        vehicle_id: '',
        mechanic_ids: [],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(route('jobs.store'));
    }

    function toggleMechanic(id: string) {
        setData(
            'mechanic_ids',
            data.mechanic_ids.includes(id)
                ? data.mechanic_ids.filter((m) => m !== id)
                : [...data.mechanic_ids, id],
        );
    }

    return (
        <GarageLayout title="New Job">
            <Head title="New Job" />
            <div className="max-w-md bg-white rounded-lg border border-gray-200 p-6">
                <form onSubmit={submit} className="space-y-5">
                    <div>
                        <label htmlFor="vehicle_id" className={label}>
                            Vehicle <span className="text-red-500">*</span>
                        </label>
                        <select
                            id="vehicle_id"
                            className={field}
                            value={data.vehicle_id}
                            onChange={(e) => setData('vehicle_id', e.target.value)}
                            required
                        >
                            <option value="">Select a vehicle…</option>
                            {vehicles.map((v) => (
                                <option key={v.id} value={v.id}>{v.registration} — {v.make} {v.model}</option>
                            ))}
                        </select>
                        {errors.vehicle_id && <p className={err}>{errors.vehicle_id}</p>}
                    </div>

                    <fieldset>
                        <legend className={label}>
                            Assigned mechanics <span className="text-red-500">*</span>
                        </legend>
                        {mechanics.length === 0 ? (
                            <p className={hint}>No active mechanics in this garage — add one before creating a job.</p>
                        ) : (
                            <div className="space-y-2 border border-gray-200 rounded-md p-3">
                                {mechanics.map((m) => (
                                    <label key={m.id} className="flex items-center gap-2 cursor-pointer text-sm">
                                        <input
                                            type="checkbox"
                                            className="rounded border-gray-300"
                                            checked={data.mechanic_ids.includes(m.id)}
                                            onChange={() => toggleMechanic(m.id)}
                                        />
                                        <span className="text-gray-900">{m.name}</span>
                                        <span className="text-xs text-gray-500">{m.role === 'garage_admin' ? 'Admin' : 'Mechanic'}</span>
                                    </label>
                                ))}
                            </div>
                        )}
                        <p className={hint}>At least one mechanic must own the job before it can be created.</p>
                        {errors.mechanic_ids && <p className={err}>{errors.mechanic_ids}</p>}
                        {errors['mechanic_ids.0'] && <p className={err}>{errors['mechanic_ids.0']}</p>}
                    </fieldset>

                    <div className="flex justify-end pt-2">
                        <Button type="submit" disabled={processing || mechanics.length === 0}>
                            {processing ? 'Creating…' : 'Create Job'}
                        </Button>
                    </div>
                </form>
            </div>
        </GarageLayout>
    );
}
