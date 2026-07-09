import { Head } from '@inertiajs/react';
import { AlertTriangle, CheckCircle, ClipboardCopy } from 'lucide-react';
import { useState } from 'react';
import ThemeToggle from '@/Components/ThemeToggle';

interface Props {
    callbackUrl: string;
    missing: string[];
}

function CopyButton({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);

    function copy() {
        navigator.clipboard.writeText(value).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <button
            onClick={copy}
            className="ml-2 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
        >
            {copied ? (
                <><CheckCircle className="h-3.5 w-3.5" /> Copied</>
            ) : (
                <><ClipboardCopy className="h-3.5 w-3.5" /> Copy</>
            )}
        </button>
    );
}

export default function SsoSetup({ callbackUrl, missing }: Props) {
    return (
        <div className="min-h-screen bg-gray-50 dark:bg-slate-950 flex items-center justify-center px-4">
            <Head title="SSO Setup Required" />

            <div className="absolute top-4 right-4">
                <ThemeToggle />
            </div>

            <div className="max-w-lg w-full">
                <div className="bg-white dark:bg-slate-900 rounded-lg border border-amber-200 dark:border-amber-900/60 shadow-sm p-6">
                    <div className="flex items-start gap-3 mb-5">
                        <AlertTriangle className="h-5 w-5 text-amber-500 dark:text-amber-400 mt-0.5 shrink-0" />
                        <div>
                            <h1 className="text-base font-semibold text-gray-900 dark:text-white">SSO not configured</h1>
                            <p className="text-sm text-gray-500 dark:text-slate-400 mt-0.5">
                                This app requires Single Sign-On. Complete the steps below to connect it.
                            </p>
                        </div>
                    </div>

                    {missing.length > 0 && (
                        <div className="mb-5 p-3 bg-amber-50 dark:bg-amber-950/40 rounded border border-amber-200 dark:border-amber-900/60">
                            <p className="text-xs font-medium text-amber-800 dark:text-amber-300 mb-1.5">Missing environment variables:</p>
                            <ul className="space-y-0.5">
                                {missing.map((v) => (
                                    <li key={v} className="text-xs font-mono text-amber-700 dark:text-amber-400">• {v}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <ol className="space-y-4 text-sm text-gray-700 dark:text-slate-300">
                        <li>
                            <span className="font-medium">1. Register this app in SSO admin</span>
                            <p className="text-gray-500 dark:text-slate-400 mt-0.5">
                                Go to{' '}
                                <span className="font-mono text-xs bg-gray-100 dark:bg-slate-800 px-1 py-0.5 rounded">
                                    auth.inte.team/admin/clients
                                </span>{' '}
                                and create a new OAuth client. Use <em>Authorization Code</em> grant.
                            </p>
                        </li>

                        <li>
                            <span className="font-medium">2. Set the redirect / callback URL</span>
                            <div className="mt-1.5 flex items-center gap-1 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded px-3 py-2">
                                <span className="font-mono text-xs text-gray-800 dark:text-slate-200 break-all">{callbackUrl}</span>
                                <CopyButton value={callbackUrl} />
                            </div>
                        </li>

                        <li>
                            <span className="font-medium">3. Add the credentials to <code className="text-xs bg-gray-100 dark:bg-slate-800 px-1 py-0.5 rounded">.env</code></span>
                            <pre className="mt-1.5 text-xs bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded px-3 py-2 overflow-x-auto text-gray-800 dark:text-slate-200">
{`SSO_URL=https://auth.inte.team
SSO_CLIENT_ID=<client id from SSO admin>
SSO_CLIENT_SECRET=<client secret from SSO admin>`}
                            </pre>
                            <p className="text-xs text-gray-400 dark:text-slate-500 mt-1">
                                Use the Panel's env editor or set directly on the server, then restart containers.
                            </p>
                        </li>

                        <li>
                            <span className="font-medium">4. Restart and retry</span>
                            <p className="text-gray-500 dark:text-slate-400 mt-0.5">
                                After saving the env vars, run{' '}
                                <code className="text-xs bg-gray-100 dark:bg-slate-800 px-1 py-0.5 rounded">docker compose restart php-fpm</code>{' '}
                                (or trigger a re-deploy from Panel), then reload this page.
                            </p>
                        </li>
                    </ol>
                </div>

                <p className="text-center text-xs text-gray-400 dark:text-slate-500 mt-4">
                    inteteam-garage · SSO setup guide
                </p>
            </div>
        </div>
    );
}
