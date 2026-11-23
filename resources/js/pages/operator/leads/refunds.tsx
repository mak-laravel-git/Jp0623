import { Head, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import { refunds as leadsRefunds } from '@/routes/leads';

interface Refund {
    id: number;
    lead_reference: string;
    lead_type: string | null;
    lead_city: string | null;
    total_amount: number;
    credit_amount: number;
    card_amount: number;
    type_label: string;
    status: 'pending' | 'completed' | 'failed';
    status_label: string;
    note: string | null;
    refunded_at: string;
}

interface CreditRow {
    id: number;
    label: string;
    note: string | null;
    amount: number;
    balance_after: number;
    created_at: string;
}

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' });

function refundDetail(refund: Refund): string {
    const parts: string[] = [];

    if (refund.credit_amount > 0) {
        parts.push(`${refund.credit_amount} Credits`);
    }

    if (refund.card_amount > 0) {
        parts.push(`$${refund.card_amount} to your card`);
    }

    return parts.join(' + ');
}

export default function LeadRefunds({
    refunds,
    creditHistory,
}: {
    refunds: Refund[];
    creditHistory: CreditRow[];
}) {
    const { credits } = usePage().props;

    return (
        <>
            <Head title="Refunds & Credits" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        Refunds &amp; Credits
                    </h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        Leads you sent back that were refunded, and your Credits.
                        1 Credit = $1 and you can use Credits when you buy a
                        lead.
                    </p>
                </div>

                <div className="rounded-xl border bg-card p-5 shadow-sm">
                    <p className="text-sm text-muted-foreground">
                        Your Credits
                    </p>
                    <p className="text-3xl font-extrabold">{credits}</p>
                </div>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Refund history</h2>
                    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-muted/50 hover:bg-muted/50">
                                        <TableHead>Lead</TableHead>
                                        <TableHead>Refunded</TableHead>
                                        <TableHead>How</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Note</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {refunds.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No refunds yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : null}
                                    {refunds.map((refund) => (
                                        <TableRow key={refund.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {refund.lead_reference}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {[
                                                        refund.lead_type,
                                                        refund.lead_city,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                ${refund.total_amount}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {refund.type_label}
                                                <p className="text-xs text-muted-foreground">
                                                    {refundDetail(refund)}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {refund.status_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm whitespace-nowrap">
                                                {formatDate(refund.refunded_at)}
                                            </TableCell>
                                            <TableCell className="max-w-xs text-sm text-muted-foreground">
                                                {refund.note ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Credits history</h2>
                    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-muted/50 hover:bg-muted/50">
                                        <TableHead>Date</TableHead>
                                        <TableHead>What</TableHead>
                                        <TableHead className="text-right">
                                            Credits
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Balance
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {creditHistory.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No Credits yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : null}
                                    {creditHistory.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="text-sm whitespace-nowrap">
                                                {formatDate(row.created_at)}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {row.label}
                                                {row.note ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        {row.note}
                                                    </p>
                                                ) : null}
                                            </TableCell>
                                            <TableCell
                                                className={`text-right font-medium ${
                                                    row.amount > 0
                                                        ? 'text-emerald-700 dark:text-emerald-300'
                                                        : ''
                                                }`}
                                            >
                                                {row.amount > 0 ? '+' : ''}
                                                {row.amount}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {row.balance_after}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

LeadRefunds.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Refunds & Credits', href: leadsRefunds() },
    ],
};
