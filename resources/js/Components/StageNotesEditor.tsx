import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Languages, Lock } from 'lucide-react';
import { FormEvent, useState } from 'react';

export interface JobStage {
    id: string;
    name: string;
    locked_at: string | null;
    notes: string | null;
    notes_translated: string | null;
    notes_source_locale: string | null;
    notes_target_locale: string | null;
    notes_translated_at: string | null;
}

interface Props {
    jobId: string;
    stages: JobStage[];
}

export function StageNotesEditor({ jobId, stages }: Props) {
    return (
        <div className="bg-white rounded-lg border border-gray-200 p-4">
            <h2 className="font-medium text-gray-900 text-sm mb-3">Stage Notes</h2>
            {stages.length === 0 ? (
                <p className="text-sm text-gray-400">No stages defined for this job.</p>
            ) : (
                <ul className="space-y-4">
                    {stages.map((stage) => (
                        <li key={stage.id}>
                            <StageRow jobId={jobId} stage={stage} />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function StageRow({ jobId, stage }: { jobId: string; stage: JobStage }) {
    const [notes, setNotes] = useState(stage.notes ?? '');
    const [saving, setSaving] = useState(false);
    const locked = stage.locked_at !== null;
    const wasTranslated = stage.notes_translated !== null
        && stage.notes_source_locale !== stage.notes_target_locale;

    function submit(e: FormEvent) {
        e.preventDefault();
        setSaving(true);
        router.patch(`/jobs/${jobId}/stages/${stage.id}/notes`, { notes }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    }

    return (
        <form onSubmit={submit} className="space-y-2">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-gray-800">{stage.name}</span>
                    {locked && (
                        <span className="inline-flex items-center gap-1 text-xs text-gray-500">
                            <Lock className="h-3 w-3" /> locked
                        </span>
                    )}
                </div>
                {wasTranslated && (
                    <span className="inline-flex items-center gap-1 text-xs text-blue-700">
                        <Languages className="h-3 w-3" />
                        Auto-translated {stage.notes_source_locale}→{stage.notes_target_locale}
                    </span>
                )}
            </div>
            <textarea
                rows={3}
                disabled={locked}
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Write notes in your working language. Customer will see an auto-translated copy."
                className="text-sm border border-gray-300 rounded-md px-3 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400"
            />
            {!locked && (
                <div className="flex justify-end">
                    <Button type="submit" size="sm" variant="outline" disabled={saving || notes === (stage.notes ?? '')}>
                        {saving ? 'Saving…' : 'Save notes'}
                    </Button>
                </div>
            )}
        </form>
    );
}
