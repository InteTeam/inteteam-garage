import { Link, usePage } from '@inertiajs/react';
import { Wrench, Car, Users, Settings, LayoutDashboard, LogOut, Menu, X } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import ThemeToggle from '@/Components/ThemeToggle';

interface Props {
    children: React.ReactNode;
    title?: string;
}

const NAV_ITEMS = [
    { href: '/',         label: 'Dashboard', icon: LayoutDashboard },
    { href: '/jobs',     label: 'Jobs',      icon: Wrench },
    { href: '/vehicles', label: 'Vehicles',  icon: Car },
    { href: '/mechanics',label: 'Mechanics', icon: Users },
    { href: '/settings', label: 'Settings',  icon: Settings },
];

export default function GarageLayout({ children, title }: Props) {
    const { url } = usePage();
    const [navOpen, setNavOpen] = useState(false);

    const navList = (
        <>
            <nav className="flex-1 py-4 px-2 space-y-1">
                {NAV_ITEMS.map(({ href, label, icon: Icon }) => (
                    <Link
                        key={href}
                        href={href}
                        onClick={() => setNavOpen(false)}
                        className={cn(
                            'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            url === href || (href !== '/' && url.startsWith(href))
                                ? 'bg-gray-100 text-gray-900 dark:bg-slate-800 dark:text-white'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-white'
                        )}
                    >
                        <Icon className="h-4 w-4" />
                        {label}
                    </Link>
                ))}
            </nav>
            <div className="p-2 border-t border-gray-200 dark:border-slate-800">
                <Link
                    href="/logout"
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
                    <Wrench className="h-5 w-5 text-gray-700 dark:text-slate-300 mr-2" />
                    <span className="font-semibold text-gray-900 dark:text-white text-sm">InteTeam Garage</span>
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
                    navOpen ? 'translate-x-0' : '-translate-x-full'
                )}
            >
                <div className="h-14 flex items-center justify-between px-4 border-b border-gray-200 dark:border-slate-800">
                    <div className="flex items-center">
                        <Wrench className="h-5 w-5 text-gray-700 dark:text-slate-300 mr-2" />
                        <span className="font-semibold text-gray-900 dark:text-white text-sm">InteTeam Garage</span>
                    </div>
                    <button
                        type="button"
                        onClick={() => setNavOpen(false)}
                        className="p-1 text-gray-500 hover:text-gray-900 dark:text-slate-400 dark:hover:text-white"
                        aria-label="Close navigation"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>
                {navList}
            </aside>

            <main className="flex-1 flex flex-col min-w-0">
                <header
                    className={cn(
                        'h-14 bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800 items-center px-4 md:px-6 gap-3',
                        title ? 'flex' : 'flex md:hidden'
                    )}
                >
                    <button
                        type="button"
                        onClick={() => setNavOpen(true)}
                        className="md:hidden p-1 -ml-1 text-gray-600 hover:text-gray-900 dark:text-slate-400 dark:hover:text-white"
                        aria-label="Open navigation"
                    >
                        <Menu className="h-5 w-5" />
                    </button>
                    {title && (
                        <h1 className="text-base font-semibold text-gray-900 dark:text-white truncate">{title}</h1>
                    )}
                    <ThemeToggle className="ml-auto" />
                </header>
                <div className="flex-1 overflow-auto p-4 md:p-6">
                    {children}
                </div>
            </main>
        </div>
    );
}
