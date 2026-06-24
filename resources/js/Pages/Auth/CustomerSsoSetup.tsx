import { Head } from '@inertiajs/react';
import { AlertTriangle, CheckCircle, ClipboardCopy } from 'lucide-react';
import { useState } from 'react';

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
            className="ml-2 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
        >
            {copied ? (
                <><CheckCircle className="h-3.5 w-3.5" /> Copied</>
            ) : (
                <><ClipboardCopy className="h-3.5 w-3.5" /> Copy</>
            )}
        </button>
    );
}

export default function CustomerSsoSetup({ callbackUrl, missing }: Props) {
    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
            <Head title="Customer Portal SSO Setup" />

            <div className="max-w-lg w-full">
                <div className="bg-white rounded-lg border border-amber-200 shadow-sm p-6">
                    <div className="flex items-start gap-3 mb-5">
                        <AlertTriangle className="h-5 w-5 text-amber-500 mt-0.5 shrink-0" />
                        <div>
                            <h1 className="text-base font-semibold text-gray-900">Customer SSO not configured</h1>
                            <p className="text-sm text-gray-500 mt-0.5">
                                The account portal needs a dedicated OAuth2 client, separate from the mechanic login.
                            </p>
                        </div>
                    </div>

                    {missing.length > 0 && (
                        <div className="mb-5 p-3 bg-amber-50 rounded border border-amber-200">
                            <p className="text-xs font-medium text-amber-800 mb-1.5">Missing environment variables:</p>
                            <ul className="space-y-0.5">
                                {missing.map((v) => (
                                    <li key={v} className="text-xs font-mono text-amber-700">• {v}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <ol className="space-y-4 text-sm text-gray-700">
                        <li>
                            <span className="font-medium">1. Create a second OAuth client in SSO admin</span>
                            <p className="text-gray-500 mt-0.5">
                                Distinct from the mechanic client. Use <em>Authorization Code</em> grant.
                            </p>
                        </li>

                        <li>
                            <span className="font-medium">2. Set the redirect / callback URL</span>
                            <div className="mt-1.5 flex items-center gap-1 bg-gray-50 border border-gray-200 rounded px-3 py-2">
                                <span className="font-mono text-xs text-gray-800 break-all">{callbackUrl}</span>
                                <CopyButton value={callbackUrl} />
                            </div>
                        </li>

                        <li>
                            <span className="font-medium">3. Add the credentials to <code className="text-xs bg-gray-100 px-1 py-0.5 rounded">.env</code></span>
                            <pre className="mt-1.5 text-xs bg-gray-50 border border-gray-200 rounded px-3 py-2 overflow-x-auto text-gray-800">
{`SSO_CUSTOMER_CLIENT_ID=<customer client id>
SSO_CUSTOMER_CLIENT_SECRET=<customer client secret>`}
                            </pre>
                        </li>

                        <li>
                            <span className="font-medium">4. Restart and retry</span>
                            <p className="text-gray-500 mt-0.5">
                                <code className="text-xs bg-gray-100 px-1 py-0.5 rounded">docker compose restart php-fpm</code>
                            </p>
                        </li>
                    </ol>
                </div>

                <p className="text-center text-xs text-gray-400 mt-4">
                    inteteam-garage · customer portal SSO setup
                </p>
            </div>
        </div>
    );
}
