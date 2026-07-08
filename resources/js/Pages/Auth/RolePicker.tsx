import { Head } from '@inertiajs/react';
import { ArrowRight, User, Wrench } from 'lucide-react';

interface Props {
    mechanicLoginUrl: string;
    customerLoginUrl: string;
}

export default function RolePicker({ mechanicLoginUrl, customerLoginUrl }: Props) {
    return (
        <div className="min-h-screen bg-slate-50 flex items-center justify-center px-4 py-10">
            <Head title="Sign in" />

            <div className="w-full max-w-lg">
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-semibold text-slate-900">How do you want to sign in?</h1>
                    <p className="mt-2 text-sm text-slate-600">
                        Both roles use your Inte.Team SSO account.
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <a
                        href={mechanicLoginUrl}
                        className="group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-900 hover:shadow-md"
                    >
                        <div className="flex items-center gap-2 text-slate-900">
                            <Wrench className="h-5 w-5" />
                            <span className="text-base font-semibold">Mechanic</span>
                        </div>
                        <p className="mt-2 text-xs text-slate-500">
                            Manage repair jobs, vehicles, and estimates.
                        </p>
                        <span className="mt-4 inline-flex items-center gap-1 text-xs font-medium text-slate-900 transition-all group-hover:gap-2">
                            Continue <ArrowRight className="h-3.5 w-3.5" />
                        </span>
                    </a>

                    <a
                        href={customerLoginUrl}
                        className="group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-900 hover:shadow-md"
                    >
                        <div className="flex items-center gap-2 text-slate-900">
                            <User className="h-5 w-5" />
                            <span className="text-base font-semibold">Customer</span>
                        </div>
                        <p className="mt-2 text-xs text-slate-500">
                            View your vehicles, estimates, and approvals.
                        </p>
                        <span className="mt-4 inline-flex items-center gap-1 text-xs font-medium text-slate-900 transition-all group-hover:gap-2">
                            Continue <ArrowRight className="h-3.5 w-3.5" />
                        </span>
                    </a>
                </div>

                <p className="text-center text-xs text-slate-400 mt-6">
                    inteteam-garage · sign in
                </p>
            </div>
        </div>
    );
}
