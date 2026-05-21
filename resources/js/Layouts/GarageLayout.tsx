import { Link, usePage } from '@inertiajs/react';
import { Wrench, Car, Users, Settings, LayoutDashboard, LogOut } from 'lucide-react';
import { cn } from '@/lib/utils';

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

    return (
        <div className="flex min-h-screen bg-gray-50">
            <aside className="w-56 bg-white border-r border-gray-200 flex flex-col">
                <div className="h-14 flex items-center px-4 border-b border-gray-200">
                    <Wrench className="h-5 w-5 text-gray-700 mr-2" />
                    <span className="font-semibold text-gray-900 text-sm">InteTeam Garage</span>
                </div>
                <nav className="flex-1 py-4 px-2 space-y-1">
                    {NAV_ITEMS.map(({ href, label, icon: Icon }) => (
                        <Link
                            key={href}
                            href={href}
                            className={cn(
                                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                url === href || (href !== '/' && url.startsWith(href))
                                    ? 'bg-gray-100 text-gray-900'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                            )}
                        >
                            <Icon className="h-4 w-4" />
                            {label}
                        </Link>
                    ))}
                </nav>
                <div className="p-2 border-t border-gray-200">
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors"
                    >
                        <LogOut className="h-4 w-4" />
                        Sign out
                    </Link>
                </div>
            </aside>
            <main className="flex-1 flex flex-col overflow-hidden">
                {title && (
                    <header className="h-14 bg-white border-b border-gray-200 flex items-center px-6">
                        <h1 className="text-base font-semibold text-gray-900">{title}</h1>
                    </header>
                )}
                <div className="flex-1 overflow-auto p-6">
                    {children}
                </div>
            </main>
        </div>
    );
}
