import { RefreshCw } from 'lucide-react';

export interface PreviewLineItem {
    id: string;
    original: string;
    translated: string;
    translated_by_ai: boolean;
    // Laravel's `decimal:2` cast serialises as a string ("324.00").
    price: number | string;
}

interface Props {
    item: PreviewLineItem;
    fromLocale: string;
    toLocale: string;
    draftText: string;
    onDraftChange: (next: string) => void;
    onRetry: () => void;
    retryDisabled: boolean;
}

export function TranslationPreviewRow({
    item, fromLocale, toLocale, draftText, onDraftChange, onRetry, retryDisabled,
}: Props) {
    return (
        <div className="grid grid-cols-2 gap-3 border border-gray-200 dark:border-slate-800 rounded-md p-3">
            <div>
                <p className="text-xs font-medium text-gray-500 dark:text-slate-400 mb-1">Original ({fromLocale})</p>
                <p className="text-sm text-gray-800 dark:text-slate-200">{item.original}</p>
                <p className="text-xs text-gray-500 dark:text-slate-400 mt-1">£{Number(item.price).toFixed(2)}</p>
            </div>
            <div>
                <div className="flex items-center justify-between mb-1">
                    <p className="text-xs font-medium text-gray-500 dark:text-slate-400">Customer sees ({toLocale})</p>
                    <button
                        type="button"
                        onClick={onRetry}
                        disabled={retryDisabled}
                        className="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 disabled:text-gray-300 dark:disabled:text-slate-600"
                    >
                        <RefreshCw className="h-3 w-3" />
                        Retry
                    </button>
                </div>
                <textarea
                    rows={2}
                    value={draftText}
                    onChange={(e) => onDraftChange(e.target.value)}
                    className="text-sm border border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 rounded-md px-2 py-1 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400"
                />
            </div>
        </div>
    );
}
