import { Link, usePage } from '@inertiajs/react';
import { Car, CreditCard, LayoutDashboard, LogOut, Menu, User, X } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import ThemeToggle from '@/Components/ThemeToggle';

interface Props {
    children: React.ReactNode;
    title?: string;
}

const NAV_ITEMS = [
    { href: '/account', label: 'Overview', icon: LayoutDashboard, exact: true },
    { href: '/account/transactions', label: 'Transactions', icon: CreditCard, exact: false },
];

interface PageProps {
    auth?: { customer?: { name: string; email: string } | null };
}

export default function CustomerLayout({ children, title }: Props) {
    const { url, props } = usePage<PageProps>();
    const [navOpen, setNavOpen] = useState(false);
    const customer = props.auth?.customer ?? null;

    const isActive = (href: string, exact: boolean) =>
        exact ? url === href : url === href || url.startsWith(href + '/');

    const navList = (
        <>
            <nav className="flex-1 py-4 px-2 space-y-1">
                {NAV_ITEMS.map(({ href, label, icon: Icon, exact }) => (
                    <Link
                        key={href}
                        href={href}
                        onClick={() => setNavOpen(false)}
                        className={cn(
                            'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            isActive(href, exact)
                                ? 'bg-gray-100 text-gray-900 dark:bg-slate-800 dark:text-white'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-white',
                        )}
                    >
                        <Icon className="h-4 w-4" />
                        {label}
                    </Link>
                ))}
            </nav>
            <div className="p-2 border-t border-gray-200 dark:border-slate-800 space-y-1">
                {customer && (
                    <div className="px-3 py-2 text-xs text-gray-500 dark:text-slate-500">
                        <div className="flex items-center gap-2">
                            <User className="h-3.5 w-3.5" />
                            <span className="truncate">{customer.name || customer.email}</span>
                        </div>
                    </div>
                )}
                <Link
                    href="/account/logout"
                    method="post"
                    as="button"
                    className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-white transition-colors"
                >
                    <LogOut className="h-4 w-4" />
                    Sign out
                </Link>
            </div>
        </>
    );

    return (
        <div className="flex min-h-screen bg-gray-50 dark:bg-slate-950">
            <aside className="hidden md:flex w-56 bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 flex-col">
                <div className="h-14 flex items-center px-4 border-b border-gray-200 dark:border-slate-800">
                    <Car className="h-5 w-5 text-gray-700 dark:text-slate-300 mr-2" />
                    <span className="font-semibold text-gray-900 dark:text-white text-sm">My Account</span>
                </div>
                {navList}
            </aside>

            {navOpen && (
                <div
                    className="md:hidden fixed inset-0 z-40 bg-black/40"
                    onClick={() => setNavOpen(false)}
                    aria-hidden
                />
            )}
            <aside
                className={cn(
                    'md:hidden fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 flex flex-col transition-transform duration-200',
                    navOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <div className="h-14 flex items-center justify-between px-4 border-b border-gray-200 dark:border-slate-800">
                    <div className="flex items-center">
                        <Car className="h-5 w-5 text-gray-700 dark:text-slate-300 mr-2" />
                        <span className="font-semibold text-gray-900 dark:text-white text-sm">My Account</span>
                    </div>
                    <button onClick={() => setNavOpen(false)} aria-label="Close menu">
                        <X className="h-5 w-5 text-gray-600 dark:text-slate-400" />
                    </button>
                </div>
                {navList}
            </aside>

            <div className="flex-1 flex flex-col min-w-0">
                <header className="h-14 bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800 flex items-center px-4 md:px-6 gap-3">
                    <button
                        onClick={() => setNavOpen(true)}
                        className="md:hidden mr-3 p-1 text-gray-600 hover:text-gray-900 dark:text-slate-400 dark:hover:text-white"
                        aria-label="Open menu"
                    >
                        <Menu className="h-5 w-5" />
                    </button>
                    {title && <h1 className="text-sm font-medium text-gray-900 dark:text-white">{title}</h1>}
                    <ThemeToggle className="ml-auto" />
                </header>

                <main className="flex-1 px-4 md:px-6 py-6 max-w-5xl w-full">{children}</main>
            </div>
        </div>
    );
}
