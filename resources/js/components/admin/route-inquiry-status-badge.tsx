import { Badge } from '@/components/ui/badge';
import type { RouteInquiryStatus } from '@/types/admin';

export const ROUTE_INQUIRY_STATUS_LABELS: Record<RouteInquiryStatus, string> = {
    pending: 'Pending',
    contacted: 'Contacted',
    closed: 'Closed',
};

export function RouteInquiryStatusBadge({
    status,
}: {
    status: RouteInquiryStatus;
}) {
    const variant =
        status === 'pending'
            ? 'secondary'
            : status === 'contacted'
              ? 'default'
              : 'outline';

    return (
        <Badge variant={variant}>
            {ROUTE_INQUIRY_STATUS_LABELS[status] ?? status}
        </Badge>
    );
}
