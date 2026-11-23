import { Badge } from '@/components/ui/badge';
import type { LeadIssueStatus } from '@/types/admin';

const STYLES: Record<LeadIssueStatus, string> = {
    open: 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
    approved:
        'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
    rejected: 'border-destructive/40 bg-destructive/10 text-destructive',
};

export const LEAD_ISSUE_STATUS_LABELS: Record<LeadIssueStatus, string> = {
    open: 'Open',
    approved: 'Refunded',
    rejected: 'Return rejected',
};

export function LeadIssueStatusBadge({ status }: { status: LeadIssueStatus }) {
    return (
        <Badge variant="outline" className={STYLES[status]}>
            {LEAD_ISSUE_STATUS_LABELS[status]}
        </Badge>
    );
}
