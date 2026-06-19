import GarageLayout from '@/Layouts/GarageLayout';
import { useForm } from '@inertiajs/react';

type Channel = 'email' | 'sms' | 'in_app';
type Recipient = 'customer' | 'customer_and_mechanic' | 'mechanic';
type ReminderType = 'mot' | 'tax' | 'insurance';

interface Garage {
    id: string;
    name: string;
    slug: string;
    online_payment_enabled: boolean;
    default_notification_channel: Channel;
    locale: string;
    compliance_reminders_enabled: boolean;
    compliance_reminders_channel: Channel | null;
    compliance_reminders_windows: number[] | null;
    compliance_reminders_recipient: Recipient;
    compliance_reminders_types: ReminderType[] | null;
}

interface Props {
    garage: Garage;
}

const WINDOW_OPTIONS: { days: number; label: string }[] = [
    { days: 30, label: '30 days before' },
    { days: 14, label: '14 days before' },
    { days: 7, label: '7 days before' },
    { days: 1, label: '1 day before' },
];

const TYPE_OPTIONS: { value: ReminderType; label: string }[] = [
    { value: 'mot', label: 'MOT' },
    { value: 'tax', label: 'Road Tax' },
    { value: 'insurance', label: 'Insurance' },
];

export default function SettingsIndex({ garage }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: garage.name,
        default_notification_channel: garage.default_notification_channel,
        online_payment_enabled: garage.online_payment_enabled,
        locale: garage.locale,
        compliance_reminders_enabled: garage.compliance_reminders_enabled,
        compliance_reminders_channel: garage.compliance_reminders_channel ?? '',
        compliance_reminders_windows: (garage.compliance_reminders_windows ?? [30, 7]) as number[],
        compliance_reminders_recipient: garage.compliance_reminders_recipient ?? 'customer',
        compliance_reminders_types: (garage.compliance_reminders_types ?? ['mot', 'tax', 'insurance']) as ReminderType[],
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put('/settings');
    }

    function toggleWindow(days: number) {
        setData(
            'compliance_reminders_windows',
            data.compliance_reminders_windows.includes(days)
                ? data.compliance_reminders_windows.filter((d) => d !== days)
                : [...data.compliance_reminders_windows, days].sort((a, b) => b - a),
        );
    }

    function toggleType(t: ReminderType) {
        setData(
            'compliance_reminders_types',
            data.compliance_reminders_types.includes(t)
                ? data.compliance_reminders_types.filter((x) => x !== t)
                : [...data.compliance_reminders_types, t],
        );
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
                            onChange={(e) => setData('default_notification_channel', e.target.value as Channel)}
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

                    <hr className="border-gray-200" />

                    <div className="space-y-4">
                        <div>
                            <h2 className="text-lg font-semibold">Compliance Reminders</h2>
                            <p className="text-xs text-gray-500">Automatic notifications when a vehicle's MOT, Road Tax, or Insurance is approaching expiry.</p>
                        </div>

                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="reminders_enabled"
                                checked={data.compliance_reminders_enabled}
                                onChange={(e) => setData('compliance_reminders_enabled', e.target.checked)}
                                className="h-4 w-4"
                            />
                            <label htmlFor="reminders_enabled" className="text-sm font-medium">
                                Send compliance reminders
                            </label>
                        </div>
                        {errors.compliance_reminders_enabled && (
                            <p className="text-red-500 text-sm mt-1">{errors.compliance_reminders_enabled}</p>
                        )}

                        {data.compliance_reminders_enabled && (
                            <div className="space-y-4 pl-6 border-l-2 border-blue-200">
                                <div>
                                    <label className="block text-sm font-medium mb-1">Channel</label>
                                    <select
                                        value={data.compliance_reminders_channel}
                                        onChange={(e) => setData('compliance_reminders_channel', e.target.value as Channel | '')}
                                        className="w-full border rounded px-3 py-2"
                                    >
                                        <option value="">Use garage default ({data.default_notification_channel})</option>
                                        <option value="email">Email</option>
                                        <option value="sms">SMS</option>
                                        <option value="in_app">In-App</option>
                                    </select>
                                    {errors.compliance_reminders_channel && (
                                        <p className="text-red-500 text-sm mt-1">{errors.compliance_reminders_channel}</p>
                                    )}
                                </div>

                                <div>
                                    <p className="block text-sm font-medium mb-2">When to send</p>
                                    <div className="space-y-1">
                                        {WINDOW_OPTIONS.map(({ days, label }) => (
                                            <label key={days} className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={data.compliance_reminders_windows.includes(days)}
                                                    onChange={() => toggleWindow(days)}
                                                    className="h-4 w-4"
                                                />
                                                {label}
                                            </label>
                                        ))}
                                    </div>
                                    {data.compliance_reminders_windows.length === 0 && (
                                        <p className="text-amber-600 text-xs mt-1">Pick at least one window.</p>
                                    )}
                                    {errors.compliance_reminders_windows && (
                                        <p className="text-red-500 text-sm mt-1">{errors.compliance_reminders_windows}</p>
                                    )}
                                </div>

                                <div>
                                    <p className="block text-sm font-medium mb-2">Compliance types to remind about</p>
                                    <div className="space-y-1">
                                        {TYPE_OPTIONS.map(({ value, label }) => (
                                            <label key={value} className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={data.compliance_reminders_types.includes(value)}
                                                    onChange={() => toggleType(value)}
                                                    className="h-4 w-4"
                                                />
                                                {label}
                                            </label>
                                        ))}
                                    </div>
                                    {data.compliance_reminders_types.length === 0 && (
                                        <p className="text-amber-600 text-xs mt-1">Pick at least one type.</p>
                                    )}
                                    {errors.compliance_reminders_types && (
                                        <p className="text-red-500 text-sm mt-1">{errors.compliance_reminders_types}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-1">Send to</label>
                                    <select
                                        value={data.compliance_reminders_recipient}
                                        onChange={(e) => setData('compliance_reminders_recipient', e.target.value as Recipient)}
                                        className="w-full border rounded px-3 py-2"
                                    >
                                        <option value="customer">Customer only</option>
                                        <option value="customer_and_mechanic">Customer and mechanic admin</option>
                                        <option value="mechanic">Mechanic admin only</option>
                                    </select>
                                    {errors.compliance_reminders_recipient && (
                                        <p className="text-red-500 text-sm mt-1">{errors.compliance_reminders_recipient}</p>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={
                            processing ||
                            (data.compliance_reminders_enabled &&
                                (data.compliance_reminders_windows.length === 0 ||
                                    data.compliance_reminders_types.length === 0))
                        }
                        className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        Save Settings
                    </button>
                </form>
            </div>
        </GarageLayout>
    );
}
