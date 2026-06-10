import { router } from '@inertiajs/react';
import * as Dialog from '@radix-ui/react-dialog';
import { Button } from '@/Components/ui/button';
import { PreviewLineItem, TranslationPreviewRow } from '@/Components/TranslationPreviewRow';
import { Languages, X } from 'lucide-react';
import { ReactNode, useState } from 'react';
import axios from 'axios';

interface PreviewResponse {
    translations: PreviewLineItem[];
    from_locale: string;
    to_locale: string;
    configured_from_locale: string;
    auto_detected_override: boolean;
}

interface Props {
    jobId: string;
    estimateId: string;
    trigger: ReactNode;
}

export function TranslationPreviewDialog({ jobId, estimateId, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [preview, setPreview] = useState<PreviewResponse | null>(null);
    const [edits, setEdits] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const previewUrl = `/jobs/${jobId}/estimates/${estimateId}/preview-translation`;

    async function loadPreview() {
        setLoading(true);
        setError(null);
        try {
            const res = await axios.post<PreviewResponse>(previewUrl);
            setPreview(res.data);
            setEdits(Object.fromEntries(res.data.translations.map((t) => [t.id, t.translated])));
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Failed to load preview');
        } finally {
            setLoading(false);
        }
    }

    async function retryItem(itemId: string) {
        if (!preview) return;
        setLoading(true);
        try {
            const res = await axios.post<PreviewResponse>(previewUrl);
            const fresh = res.data.translations.find((t) => t.id === itemId);
            if (fresh) setEdits((prev) => ({ ...prev, [itemId]: fresh.translated }));
        } finally {
            setLoading(false);
        }
    }

    function confirmAndSend() {
        if (!preview) return;
        setSubmitting(true);
        const confirmations = preview.translations.map((t) => ({
            id: t.id,
            translated_text: edits[t.id] ?? t.translated,
            llm_raw_text: t.translated,
        }));
        router.post(`/jobs/${jobId}/estimates/${estimateId}/confirm-translation`, { confirmations }, {
            preserveScroll: true,
            onSuccess: () => router.post(`/jobs/${jobId}/estimates/${estimateId}/send`, {}, {
                preserveScroll: true,
                onFinish: () => { setSubmitting(false); setOpen(false); },
            }),
            onError: () => setSubmitting(false),
        });
    }

    function handleOpenChange(next: boolean) {
        setOpen(next);
        if (next && !preview) loadPreview();
        if (!next) { setPreview(null); setEdits({}); setError(null); }
    }

    return (
        <Dialog.Root open={open} onOpenChange={handleOpenChange}>
            <Dialog.Trigger asChild>{trigger}</Dialog.Trigger>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 bg-black/40 z-40" />
                <Dialog.Content className="fixed left-1/2 top-1/2 z-50 -translate-x-1/2 -translate-y-1/2 w-full max-w-4xl max-h-[85vh] overflow-auto bg-white rounded-lg shadow-xl border border-gray-200 p-6">
                    <div className="flex items-start justify-between mb-4">
                        <div>
                            <Dialog.Title className="text-base font-semibold text-gray-900 flex items-center gap-2">
                                <Languages className="h-4 w-4" /> Translation Preview
                            </Dialog.Title>
                            <Dialog.Description className="text-sm text-gray-500 mt-1">
                                Review the customer-facing translation before sending. Edits override the AI suggestion.
                            </Dialog.Description>
                        </div>
                        <Dialog.Close asChild>
                            <button className="text-gray-400 hover:text-gray-600"><X className="h-4 w-4" /></button>
                        </Dialog.Close>
                    </div>

                    {loading && !preview && <p className="text-sm text-gray-500 py-8 text-center">Loading translation…</p>}
                    {error && <p className="text-sm text-red-600 py-4">{error}</p>}

                    {preview && (
                        <>
                            <div className="text-xs text-gray-500 mb-3 flex items-center gap-3">
                                <span>From: <span className="font-mono">{preview.from_locale}</span></span>
                                <span>→</span>
                                <span>To: <span className="font-mono">{preview.to_locale}</span></span>
                                {preview.auto_detected_override && (
                                    <span className="text-amber-700">
                                        (auto-detected from configured {preview.configured_from_locale})
                                    </span>
                                )}
                            </div>
                            <div className="space-y-3">
                                {preview.translations.map((item) => (
                                    <TranslationPreviewRow
                                        key={item.id}
                                        item={item}
                                        fromLocale={preview.from_locale}
                                        toLocale={preview.to_locale}
                                        draftText={edits[item.id] ?? ''}
                                        onDraftChange={(next) => setEdits((prev) => ({ ...prev, [item.id]: next }))}
                                        onRetry={() => retryItem(item.id)}
                                        retryDisabled={loading}
                                    />
                                ))}
                            </div>
                            <div className="flex justify-end gap-2 mt-4 pt-3 border-t border-gray-100">
                                <Dialog.Close asChild>
                                    <Button variant="outline" size="sm" disabled={submitting}>Cancel</Button>
                                </Dialog.Close>
                                <Button size="sm" onClick={confirmAndSend} disabled={submitting || loading}>
                                    {submitting ? 'Sending…' : 'Confirm & Send'}
                                </Button>
                            </div>
                        </>
                    )}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
