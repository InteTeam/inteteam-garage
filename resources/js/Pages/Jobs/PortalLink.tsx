import GarageLayout from '@/Layouts/GarageLayout';
import { Head, router } from '@inertiajs/react';

interface Job {
    id: string;
    state: string;
}

interface SignedPortalToken {
    token: string;
    expires_at: string;
    revoked_at: string | null;
}

interface Props {
    job: Job;
    portalUrl: string | null;
    token: SignedPortalToken | null;
}

export default function PortalLink({ job, portalUrl, token }: Props) {
    function handleRegenerate() {
        router.post(`/jobs/${job.id}/portal-link/regenerate`);
    }

    function handleCopy() {
        if (portalUrl) {
            navigator.clipboard.writeText(portalUrl);
        }
    }

    return (
        <GarageLayout title="Portal Link">
            <Head title="Portal Link" />
            <div className="max-w-xl">
                <div className="mb-4">
                    <a href={`/jobs/${job.id}`} className="text-blue-600 hover:underline dark:text-blue-400 text-sm">
                        ← Back to Job
                    </a>
                </div>

                <h1 className="text-2xl font-semibold mb-6">Customer Portal Link</h1>

                {portalUrl && token ? (
                    <div className="space-y-4">
                        <div className="bg-gray-50 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded p-4">
                            <p className="text-sm text-gray-600 dark:text-slate-400 mb-2">Shareable link:</p>
                            <p className="font-mono text-sm break-all">{portalUrl}</p>
                        </div>

                        <div className="text-sm text-gray-500 dark:text-slate-400">
                            Expires: {new Date(token.expires_at).toLocaleDateString()}
                            {token.revoked_at && (
                                <span className="ml-2 text-red-500 dark:text-red-400 font-medium">— Revoked</span>
                            )}
                        </div>

                        <div className="flex gap-3">
                            <button
                                onClick={handleCopy}
                                className="border border-gray-300 dark:border-slate-700 dark:text-slate-200 px-4 py-2 rounded text-sm hover:bg-gray-50 dark:hover:bg-slate-800"
                            >
                                Copy Link
                            </button>
                            <button
                                onClick={handleRegenerate}
                                className="border border-red-300 dark:border-red-900/60 text-red-600 dark:text-red-400 px-4 py-2 rounded text-sm hover:bg-red-50 dark:hover:bg-red-950/40"
                            >
                                Regenerate (revokes current link)
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <p className="text-gray-600 dark:text-slate-400">No active portal link. Generate one to share with the customer.</p>
                        <button
                            onClick={handleRegenerate}
                            className="bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400 text-white px-4 py-2 rounded"
                        >
                            Generate Portal Link
                        </button>
                    </div>
                )}
            </div>
        </GarageLayout>
    );
}
