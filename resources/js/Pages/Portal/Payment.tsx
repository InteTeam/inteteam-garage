import { Head, router } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';
import { Button } from '@/Components/ui/button';
import { CheckCircle } from 'lucide-react';

interface Job { garage: { name: string } }
interface Props { job: Job; token: string; amount: number; paymentConfirmed: boolean }

export default function PortalPayment({ job, token, amount, paymentConfirmed }: Props) {
    function requestPayment() {
        router.post(`/portal/${token}/payment/request`);
    }

    return (
        <PortalLayout title="Payment" garageName={job.garage.name}>
            <Head title="Payment" />
            {paymentConfirmed ? (
                <div className="flex flex-col items-center py-8 gap-3 text-center">
                    <CheckCircle className="h-12 w-12 text-green-500" />
                    <p className="text-lg font-semibold text-gray-900">Payment Confirmed</p>
                    <p className="text-sm text-gray-500">Thank you — your payment has been received.</p>
                </div>
            ) : (
                <div className="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
                    <p className="text-sm text-gray-600">
                        Your approved repairs total:
                    </p>
                    <p className="text-3xl font-bold text-gray-900">£{amount.toFixed(2)}</p>
                    <p className="text-xs text-gray-500">
                        This amount covers only the items you approved. Declined items are not included.
                    </p>
                    <Button onClick={requestPayment} className="w-full">
                        Pay Now
                    </Button>
                </div>
            )}
        </PortalLayout>
    );
}
