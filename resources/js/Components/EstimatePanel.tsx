import { router, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { TranslationPreviewDialog } from '@/Components/TranslationPreviewDialog';
import { Plus } from 'lucide-react';
import { FormEvent } from 'react';

// price arrives as a string from Laravel's `decimal:2` cast — keep the type
// honest so .toFixed / arithmetic call sites are forced to coerce.
interface LineItem { id: string; description: string; price: number | string; status: string }
export interface Estimate {
    id: string;
    revision_number: number;
    sent_at: string | null;
    preview_confirmed_at: string | null;
    line_items: LineItem[];
}
interface Props { jobId: string; estimate: Estimate }

export function EstimatePanel({ jobId, estimate }: Props) {
    const total = estimate.line_items.reduce((s, i) => s + Number(i.price), 0);
    // Mirror EstimateService::addLineItem guards. The estimate seals on send
    // (customer may be mid-read in the portal) and again on the first
    // approve/decline (planning.md L175). Either way: raise a scope change.
    const canAddLineItems =
        !estimate.sent_at && !estimate.line_items.some((i) => i.status !== 'pending');
    return (
        <div className="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-4">
            <div className="flex items-center justify-between mb-3">
                <h2 className="font-medium text-gray-900 dark:text-white text-sm">Estimate #{estimate.revision_number}</h2>
                {!estimate.sent_at && (
                    estimate.preview_confirmed_at ? (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => router.post(`/jobs/${jobId}/estimates/${estimate.id}/send`)}
                        >
                            Send to Customer
                        </Button>
                    ) : (
                        <TranslationPreviewDialog
                            jobId={jobId}
                            estimateId={estimate.id}
                            trigger={<Button size="sm" variant="outline">Preview &amp; Send</Button>}
                        />
                    )
                )}
            </div>
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-gray-100 dark:border-slate-800">
                        <th className="pb-2 text-left font-medium text-gray-600 dark:text-slate-400">Description</th>
                        <th className="pb-2 text-right font-medium text-gray-600 dark:text-slate-400">Price</th>
                        <th className="pb-2 text-right font-medium text-gray-600 dark:text-slate-400">Status</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50 dark:divide-slate-800">
                    {estimate.line_items.map((item) => (
                        <tr key={item.id}>
                            <td className="py-2 text-gray-800 dark:text-slate-200">{item.description}</td>
                            <td className="py-2 text-right text-gray-800 dark:text-slate-200">£{Number(item.price).toFixed(2)}</td>
                            <td className="py-2 text-right">
                                <span className={`text-xs font-medium ${
                                    item.status === 'approved' ? 'text-green-600 dark:text-emerald-400' :
                                    item.status === 'declined' ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-slate-400'
                                }`}>
                                    {item.status}
                                </span>
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr>
                        <td colSpan={2} className="pt-3 text-right text-sm font-semibold text-gray-900 dark:text-white">
                            Total: £{total.toFixed(2)}
                        </td>
                    </tr>
                </tfoot>
            </table>
            {canAddLineItems && (
                <AddLineItemForm jobId={jobId} estimateId={estimate.id} />
            )}
        </div>
    );
}

function AddLineItemForm({ jobId, estimateId }: { jobId: string; estimateId: string }) {
    const form = useForm({ description: '', price: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/jobs/${jobId}/estimates/${estimateId}/line-items`, {
            preserveScroll: true,
            onSuccess: () => form.reset('description', 'price'),
        });
    }

    return (
        <form onSubmit={submit} className="mt-4 pt-4 border-t border-gray-100 dark:border-slate-800 space-y-2">
            <div className="flex flex-col sm:flex-row gap-2">
                <input
                    type="text"
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
                    placeholder="Line item description"
                    maxLength={500}
                    required
                    className="text-sm border border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 rounded-md px-3 py-2 flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400"
                />
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    value={form.data.price}
                    onChange={(e) => form.setData('price', e.target.value)}
                    placeholder="Price"
                    required
                    className="text-sm border border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 rounded-md px-3 py-2 sm:w-28 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400"
                />
                <Button
                    type="submit"
                    size="sm"
                    disabled={form.processing || form.data.description.trim() === '' || form.data.price === ''}
                >
                    <Plus className="h-3.5 w-3.5" />
                    Add
                </Button>
            </div>
            {(form.errors.description || form.errors.price) && (
                <p className="text-xs text-red-600 dark:text-red-400">
                    {form.errors.description ?? form.errors.price}
                </p>
            )}
        </form>
    );
}
