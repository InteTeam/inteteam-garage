import { Head, useForm } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { FormEvent } from 'react';

interface Vehicle {
    id: string;
    crm_customer_id: string;
    registration: string;
    make: string;
    model: string;
    year: string;
    colour: string;
}

interface Props {
    vehicle?: Vehicle | null;
    returningCustomerIds?: string[];
}

const field = 'text-sm border border-gray-300 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500';
const label = 'block text-sm font-medium text-gray-700 mb-1';
const err = 'text-xs text-red-600 mt-1';
const hint = 'text-xs text-gray-500 mt-1';

const nextYear = new Date().getFullYear() + 1;

export default function VehicleForm({ vehicle, returningCustomerIds = [] }: Props) {
    const { data, setData, post, put, processing, errors } = useForm({
        crm_customer_id: vehicle?.crm_customer_id ?? '',
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
                    <div>
                        <label htmlFor="crm_customer_id" className={label}>
                            CRM Customer ID <span className="text-red-500">*</span>
                        </label>
                        <input
                            id="crm_customer_id"
                            type="text"
                            className={field}
                            value={data.crm_customer_id}
                            onChange={(e) => setData('crm_customer_id', e.target.value)}
                            list="returning-customer-ids"
                            required
                        />
                        {returningCustomerIds.length > 0 && (
                            <datalist id="returning-customer-ids">
                                {returningCustomerIds.map((id) => <option key={id} value={id} />)}
                            </datalist>
                        )}
                        <p className={hint}>
                            Paste the customer's ID from CRM. Returning customers appear in the autocomplete.
                        </p>
                        {errors.crm_customer_id && <p className={err}>{errors.crm_customer_id}</p>}
                    </div>
                    {(['registration', 'make', 'model'] as const).map((f) => (
                        <div key={f}>
                            <label htmlFor={f} className={label}>
                                {f.charAt(0).toUpperCase() + f.slice(1)} <span className="text-red-500">*</span>
                            </label>
                            <input
                                id={f}
                                type="text"
                                className={field}
                                value={data[f]}
                                onChange={(e) => setData(f, e.target.value)}
                                required
                            />
                            {errors[f] && <p className={err}>{errors[f]}</p>}
                        </div>
                    ))}
                    <div>
                        <label htmlFor="year" className={label}>
                            Year <span className="text-red-500">*</span>
                        </label>
                        <input
                            id="year"
                            type="number"
                            className={field}
                            value={data.year}
                            onChange={(e) => setData('year', e.target.value)}
                            min={1900}
                            max={nextYear}
                            required
                        />
                        {errors.year && <p className={err}>{errors.year}</p>}
                    </div>
                    <div>
                        <label htmlFor="colour" className={label}>Colour</label>
                        <input
                            id="colour"
                            type="text"
                            className={field}
                            value={data.colour}
                            onChange={(e) => setData('colour', e.target.value)}
                        />
                        {errors.colour && <p className={err}>{errors.colour}</p>}
                    </div>
                    <div className="flex justify-end pt-2">
                        <Button type="submit" disabled={processing}>{processing ? 'Saving…' : (vehicle ? 'Update' : 'Create')}</Button>
                    </div>
                </form>
            </div>
        </GarageLayout>
    );
}
