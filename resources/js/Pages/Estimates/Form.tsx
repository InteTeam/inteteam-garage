import { Head, useForm } from '@inertiajs/react';
import GarageLayout from '@/Layouts/GarageLayout';
import { Button } from '@/Components/ui/button';
import { FormEvent } from 'react';

interface Estimate { id: string; revision_number: number; job_id: string }
interface Props { estimate?: Estimate | null; jobId?: string }

const field = 'text-sm border border-gray-300 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500';

export default function EstimateForm({ estimate, jobId }: Props) {
    const { post, processing } = useForm({});

    function submit(e: FormEvent) {
        e.preventDefault();
        post(route('jobs.estimates.store', { job: jobId ?? estimate?.job_id }));
    }

    return (
        <GarageLayout title="New Estimate">
            <Head title="New Estimate" />
            <div className="max-w-md bg-white rounded-lg border border-gray-200 p-6">
                <form onSubmit={submit} className="space-y-4">
                    <p className="text-sm text-gray-600">Create a new estimate revision for this job.</p>
                    <div className="flex justify-end pt-2">
                        <Button type="submit" disabled={processing}>{processing ? 'Creating…' : 'Create Estimate'}</Button>
                    </div>
                </form>
            </div>
        </GarageLayout>
    );
}
