import GarageLayout from '@/Layouts/GarageLayout';
import { useForm } from '@inertiajs/react';

interface Garage {
    id: string;
    name: string;
    slug: string;
    online_payment_enabled: boolean;
    default_notification_channel: string;
    locale: string;
}

interface Props {
    garage: Garage;
}

export default function SettingsIndex({ garage }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: garage.name,
        default_notification_channel: garage.default_notification_channel,
        online_payment_enabled: garage.online_payment_enabled,
        locale: garage.locale,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put('/settings');
    }

    return (
        <GarageLayout title="Settings">
            <div className="max-w-xl">
                <h1 className="text-2xl font-semibold mb-6">Garage Settings</h1>
                <form onSubmit={handleSubmit} className="space-y-5">
                    <div>
                        <label className="block text-sm font-medium mb-1">Garage Name</label>
                        <input
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="w-full border rounded px-3 py-2"
                        />
                        {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium mb-1">Default Notification Channel</label>
                        <select
                            value={data.default_notification_channel}
                            onChange={(e) => setData('default_notification_channel', e.target.value)}
                            className="w-full border rounded px-3 py-2"
                        >
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                            <option value="in_app">In-App</option>
                        </select>
                        {errors.default_notification_channel && (
                            <p className="text-red-500 text-sm mt-1">{errors.default_notification_channel}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium mb-1">Mechanic Locale</label>
                        <select
                            value={data.locale}
                            onChange={(e) => setData('locale', e.target.value)}
                            className="w-full border rounded px-3 py-2"
                        >
                            <option value="en">English</option>
                            <option value="pl">Polish</option>
                        </select>
                        {errors.locale && <p className="text-red-500 text-sm mt-1">{errors.locale}</p>}
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="online_payment"
                            checked={data.online_payment_enabled}
                            onChange={(e) => setData('online_payment_enabled', e.target.checked)}
                            className="h-4 w-4"
                        />
                        <label htmlFor="online_payment" className="text-sm font-medium">
                            Enable Online Payment (via CRM)
                        </label>
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        Save Settings
                    </button>
                </form>
            </div>
        </GarageLayout>
    );
}
