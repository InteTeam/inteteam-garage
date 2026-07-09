import { Wrench } from 'lucide-react';
import ThemeToggle from '@/Components/ThemeToggle';

interface Props {
    children: React.ReactNode;
    title?: string;
    garageName?: string;
}

export default function PortalLayout({ children, title, garageName }: Props) {
    return (
        <div className="min-h-screen bg-gray-50 dark:bg-slate-950">
            <header className="bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800">
                <div className="max-w-2xl mx-auto px-4 h-14 flex items-center gap-2">
                    <Wrench className="h-5 w-5 text-gray-700 dark:text-slate-300" />
                    <span className="font-semibold text-gray-900 dark:text-white text-sm">
                        {garageName ?? 'Garage Portal'}
                    </span>
                    <ThemeToggle className="ml-auto" />
                </div>
            </header>
            <main className="max-w-2xl mx-auto px-4 py-8">
                {title && (
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-white mb-6">{title}</h1>
                )}
                {children}
            </main>
        </div>
    );
}
