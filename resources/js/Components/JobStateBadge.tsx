import { Badge } from '@/Components/ui/badge';

const STATE_CONFIG: Record<string, { label: string; variant: 'default' | 'secondary' | 'info' | 'warning' | 'success' | 'destructive' }> = {
    created:             { label: 'Created',            variant: 'secondary' },
    booked:              { label: 'Booked',             variant: 'info' },
    in_progress:         { label: 'In Progress',        variant: 'warning' },
    awaiting_approval:   { label: 'Awaiting Approval',  variant: 'warning' },
    customer_query:      { label: 'Customer Query',     variant: 'warning' },
    scope_change:        { label: 'Scope Change',       variant: 'warning' },
    approved:            { label: 'Approved',           variant: 'success' },
    completed:           { label: 'Completed',          variant: 'success' },
    awaiting_collection: { label: 'Awaiting Collection', variant: 'info' },
    collected:           { label: 'Collected',          variant: 'success' },
};

export function JobStateBadge({ state }: { state: string }) {
    const config = STATE_CONFIG[state] ?? { label: state, variant: 'secondary' as const };
    return <Badge variant={config.variant}>{config.label}</Badge>;
}
