import { Wrench } from 'lucide-react';

interface Props {
    children: React.ReactNode;
    title?: string;
    garageName?: string;
}

export default function PortalLayout({ children, title, garageName }: Props) {
    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b border-gray-200">
                <div className="max-w-2xl mx-auto px-4 h-14 flex items-center gap-2">
                    <Wrench className="h-5 w-5 text-gray-700" />
                    <span className="font-semibold text-gray-900 text-sm">
                        {garageName ?? 'Garage Portal'}
                    </span>
                </div>
            </header>
            <main className="max-w-2xl mx-auto px-4 py-8">
                {title && (
                    <h1 className="text-xl font-semibold text-gray-900 mb-6">{title}</h1>
                )}
                {children}
            </main>
        </div>
    );
}
