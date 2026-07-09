import * as React from 'react';
import * as ToastPrimitive from '@radix-ui/react-toast';
import { cn } from '@/lib/utils';

const ToastProvider = ToastPrimitive.Provider;
const ToastViewport = React.forwardRef<
    React.ElementRef<typeof ToastPrimitive.Viewport>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitive.Viewport>
>(({ className, ...props }, ref) => (
    <ToastPrimitive.Viewport
        ref={ref}
        className={cn(
            'fixed bottom-0 right-0 z-50 flex max-h-screen w-full flex-col-reverse gap-2 p-4 sm:max-w-sm',
            className
        )}
        {...props}
    />
));
ToastViewport.displayName = ToastPrimitive.Viewport.displayName;

const Toast = React.forwardRef<
    React.ElementRef<typeof ToastPrimitive.Root>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitive.Root> & { variant?: 'default' | 'success' | 'destructive' }
>(({ className, variant = 'default', ...props }, ref) => (
    <ToastPrimitive.Root
        ref={ref}
        className={cn(
            'group pointer-events-auto relative flex w-full items-center justify-between overflow-hidden rounded-md border px-4 py-3 shadow-lg',
            variant === 'success' && 'border-green-200 bg-green-50 text-green-900 dark:border-emerald-900/60 dark:bg-emerald-950/60 dark:text-emerald-100',
            variant === 'destructive' && 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/60 dark:bg-red-950/60 dark:text-red-100',
            variant === 'default' && 'border bg-white text-gray-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100',
            className
        )}
        {...props}
    />
));
Toast.displayName = ToastPrimitive.Root.displayName;

const ToastTitle = React.forwardRef<
    React.ElementRef<typeof ToastPrimitive.Title>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitive.Title>
>(({ className, ...props }, ref) => (
    <ToastPrimitive.Title ref={ref} className={cn('text-sm font-semibold', className)} {...props} />
));
ToastTitle.displayName = ToastPrimitive.Title.displayName;

const ToastClose = React.forwardRef<
    React.ElementRef<typeof ToastPrimitive.Close>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitive.Close>
>(({ className, ...props }, ref) => (
    <ToastPrimitive.Close
        ref={ref}
        className={cn('absolute right-2 top-2 rounded-sm opacity-70 hover:opacity-100', className)}
        toast-close=""
        {...props}
    >
        <span className="sr-only">Close</span>
        ×
    </ToastPrimitive.Close>
));
ToastClose.displayName = ToastPrimitive.Close.displayName;

export function Toaster() {
    return (
        <ToastProvider>
            <ToastViewport />
        </ToastProvider>
    );
}

export { Toast, ToastTitle, ToastClose, ToastProvider, ToastViewport };
