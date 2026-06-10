import { Head, useForm } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { FormEvent } from 'react';

interface Mechanic {
    id: string;
    role: string;
    is_active: boolean;
    locale: string | null;
    channel_toggle_allowed: boolean | null;
    user_id: number;
}
interface AvailableUser { id: number; name: string; email: string }
interface Props {
    mechanic?: Mechanic | null;
    locales: string[];
    availableUsers?: AvailableUser[];
}

const field = 'text-sm border border-gray-300 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500';
const label = 'block text-sm font-medium text-gray-700 mb-1';
const err = 'text-xs text-red-600 mt-1';
const help = 'text-xs text-gray-500 mt-1';

export default function MechanicForm({ mechanic, locales, availableUsers = [] }: Props) {
    const isEdit = mechanic !== null && mechanic !== undefined;
    const { data, setData, post, put, processing, errors } = useForm({
        user_id: mechanic?.user_id ?? (availableUsers[0]?.id ?? 0),
        role: mechanic?.role ?? 'mechanic',
        is_active: mechanic?.is_active ?? true,
        locale: mechanic?.locale ?? '',
        channel_toggle_allowed: mechanic?.channel_toggle_allowed ?? false,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        if (mechanic) { put(route('mechanics.update', mechanic.id)); }
        else { post(route('mechanics.store')); }
    }

    return (
        <GarageLayout title={isEdit ? 'Edit Mechanic' : 'Add Mechanic'}>
            <Head title={isEdit ? 'Edit Mechanic' : 'Add Mechanic'} />
            <div className="max-w-md bg-white rounded-lg border border-gray-200 p-6">
                <form onSubmit={submit} className="space-y-4">
                    {!isEdit && (
                        <div>
                            <label htmlFor="user_id" className={label}>SSO user</label>
                            {availableUsers.length === 0 ? (
                                <p className="text-sm text-amber-700">
                                    No unassigned users — every existing user already has a Mechanic record.
                                </p>
                            ) : (
                                <select
                                    id="user_id"
                                    className={field}
                                    value={data.user_id}
                                    onChange={(e) => setData('user_id', Number(e.target.value))}
                                >
                                    {availableUsers.map((u) => (
                                        <option key={u.id} value={u.id}>{u.name} — {u.email}</option>
                                    ))}
                                </select>
                            )}
                            {errors.user_id && <p className={err}>{errors.user_id}</p>}
                        </div>
                    )}
                    <div>
                        <label htmlFor="role" className={label}>Role</label>
                        <select id="role" className={field} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                            <option value="mechanic">Mechanic</option>
                            <option value="garage_admin">Garage Admin</option>
                        </select>
                        {errors.role && <p className={err}>{errors.role}</p>}
                    </div>
                    <div>
                        <label htmlFor="locale" className={label}>Working language</label>
                        <select
                            id="locale"
                            className={field}
                            value={data.locale ?? ''}
                            onChange={(e) => setData('locale', e.target.value)}
                        >
                            <option value="">Use garage default</option>
                            {locales.map((l) => (
                                <option key={l} value={l}>{l.toUpperCase()}</option>
                            ))}
                        </select>
                        <p className={help}>Mechanic writes in this language; customer messages are auto-translated to the customer's language.</p>
                        {errors.locale && <p className={err}>{errors.locale}</p>}
                    </div>
                    <div className="flex items-start gap-2">
                        <input
                            id="channel_toggle_allowed"
                            type="checkbox"
                            className="mt-1"
                            checked={data.channel_toggle_allowed ?? false}
                            onChange={(e) => setData('channel_toggle_allowed', e.target.checked)}
                        />
                        <div>
                            <label htmlFor="channel_toggle_allowed" className="text-sm font-medium text-gray-700">
                                Allow this mechanic to opt out of email/SMS alerts
                            </label>
                            <p className={help}>In-app dashboard alerts are always on. Leave unchecked to lock all channels for safety-critical work.</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <input id="is_active" type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        <label htmlFor="is_active" className="text-sm font-medium text-gray-700">Active</label>
                    </div>
                    <div className="flex justify-end pt-2">
                        <Button
                            type="submit"
                            disabled={processing || (!isEdit && availableUsers.length === 0)}
                        >
                            {processing ? 'Saving…' : (isEdit ? 'Update' : 'Create')}
                        </Button>
                    </div>
                </form>
            </div>
        </GarageLayout>
    );
}
