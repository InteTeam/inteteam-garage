import './bootstrap';
import '../css/app.css';
import { route } from 'ziggy-js';

declare global { function route(...args: Parameters<typeof route>): ReturnType<typeof route>; }
(window as any).route = route;

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { Toaster } from '@/Components/ui/toaster';

const appName = import.meta.env.VITE_APP_NAME || 'InteTeam Garage';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx')
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <>
                <App {...props} />
                <Toaster />
            </>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
