import { Head, useForm } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { FormEvent } from 'react';

interface Mechanic { id: string; role: string; is_active: boolean }
interface Props { mechanic?: Mechanic | null }

const field = 'text-sm border border-gray-300 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500';
const label = 'block text-sm font-medium text-gray-700 mb-1';
const err = 'text-xs text-red-600 mt-1';

export default function MechanicForm({ mechanic }: Props) {
    const { data, setData, post, put, processing, errors } = useForm({
        role: mechanic?.role ?? 'mechanic',
        is_active: mechanic?.is_active ?? true,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        if (mechanic) { put(route('mechanics.update', mechanic.id)); }
        else { post(route('mechanics.store')); }
    }

    return (
        <GarageLayout title={mechanic ? 'Edit Mechanic' : 'Add Mechanic'}>
            <Head title={mechanic ? 'Edit Mechanic' : 'Add Mechanic'} />
            <div className="max-w-md bg-white rounded-lg border border-gray-200 p-6">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label htmlFor="role" className={label}>Role</label>
                        <select id="role" className={field} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                            <option value="mechanic">Mechanic</option>
                            <option value="garage_admin">Garage Admin</option>
                        </select>
                        {errors.role && <p className={err}>{errors.role}</p>}
                    </div>
                    <div className="flex items-center gap-2">
                        <input id="is_active" type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        <label htmlFor="is_active" className="text-sm font-medium text-gray-700">Active</label>
                    </div>
                    <div className="flex justify-end pt-2">
                        <Button type="submit" disabled={processing}>{processing ? 'Saving…' : (mechanic ? 'Update' : 'Create')}</Button>
                    </div>
                </form>
            </div>
        </GarageLayout>
    );
}
