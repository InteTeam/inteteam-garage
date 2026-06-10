import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { TranslationPreviewDialog } from '@/Components/TranslationPreviewDialog';

interface LineItem { id: string; description: string; price: number; status: string }
export interface Estimate {
    id: string;
    revision_number: number;
    sent_at: string | null;
    preview_confirmed_at: string | null;
    line_items: LineItem[];
}
interface Props { jobId: string; estimate: Estimate }

export function EstimatePanel({ jobId, estimate }: Props) {
    const total = estimate.line_items.reduce((s, i) => s + i.price, 0);
    return (
        <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between mb-3">
                <h2 className="font-medium text-gray-900 text-sm">Estimate #{estimate.revision_number}</h2>
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
                    <tr className="border-b border-gray-100">
                        <th className="pb-2 text-left font-medium text-gray-600">Description</th>
                        <th className="pb-2 text-right font-medium text-gray-600">Price</th>
                        <th className="pb-2 text-right font-medium text-gray-600">Status</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {estimate.line_items.map((item) => (
                        <tr key={item.id}>
                            <td className="py-2 text-gray-800">{item.description}</td>
                            <td className="py-2 text-right text-gray-800">£{item.price.toFixed(2)}</td>
                            <td className="py-2 text-right">
                                <span className={`text-xs font-medium ${
                                    item.status === 'approved' ? 'text-green-600' :
                                    item.status === 'declined' ? 'text-red-600' : 'text-gray-500'
                                }`}>
                                    {item.status}
                                </span>
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr>
                        <td colSpan={2} className="pt-3 text-right text-sm font-semibold text-gray-900">
                            Total: £{total.toFixed(2)}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
