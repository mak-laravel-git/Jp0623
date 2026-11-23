import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import { index as billingIndex } from '@/routes/billing';
import { refunds as leadsRefunds } from '@/routes/leads';

interface LastPaidCard {
    brand: string;
    last4: string;
}

interface Invoice {
    id: string;
    source?: 'lead' | 'membership' | 'refund';
    location: string | null;
    date: string;
    status: string;
    description: string;
    amount: number;
    credits_used?: number;
    credits_refunded?: number;
    qty: number;
    receipt_url: string | null;
    card_brand: string | null;
    card_last4: string | null;
}

function money(value: number): string {
    return `${value < 0 ? '-' : ''}$${Math.abs(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

/**
 * Extra lines about Credits for a row: Credits used to pay, or Credits given back by a refund.
 */
function creditsNote(row: Invoice): string | null {
    if ((row.credits_used ?? 0) > 0) {
        return `${row.credits_used} Credits used`;
    }

    if ((row.credits_refunded ?? 0) > 0) {
        return `${row.credits_refunded} Credits back`;
    }

    return null;
}

function statusBadge(status: string) {
    if (status === 'Paid' || status === 'Completed') {
        return (
            <Badge
                variant="outline"
                className="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200"
            >
                {status}
            </Badge>
        );
    }

    if (status === 'Refunded' || status === 'Processing') {
        return (
            <Badge
                variant="outline"
                className="border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200"
            >
                {status}
            </Badge>
        );
    }

    if (status === 'Failed') {
        return (
            <Badge
                variant="outline"
                className="border-destructive/40 bg-destructive/10 text-destructive"
            >
                {status}
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="text-muted-foreground">
            {status}
        </Badge>
    );
}

export default function BillingIndex({
    lastPaidCard,
    invoices,
}: {
    lastPaidCard: LastPaidCard | null;
    invoices: Invoice[];
}) {
    const [invoice, setInvoice] = useState<Invoice | null>(null);
    const { credits } = usePage().props;

    return (
        <>
            <Head title="Billing & invoices" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        Billing &amp; invoices
                    </h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        Lead purchases, membership charges and refunds.
                        Membership cards stay on file for renewals; lead
                        checkouts are one-time.
                    </p>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card px-4 py-4 text-sm shadow-sm">
                    <span>
                        Your Credits: <strong>{credits}</strong> (1 Credit = $1)
                    </span>
                    <Link
                        href={leadsRefunds().url}
                        className="text-brass underline-offset-2 hover:underline"
                    >
                        Refunds &amp; Credits
                    </Link>
                </div>

                {lastPaidCard ? (
                    <div className="rounded-xl border bg-card px-4 py-4 text-sm shadow-sm">
                        Last card used:{' '}
                        <strong>
                            {lastPaidCard.brand} ending {lastPaidCard.last4}
                        </strong>
                    </div>
                ) : null}

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/50 hover:bg-muted/50">
                                    <TableHead>ID</TableHead>
                                    <TableHead>Location</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Qty
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            No paid invoices yet.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    invoices.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="font-mono text-xs">
                                                {row.id}
                                            </TableCell>
                                            <TableCell>
                                                {row.location ?? '—'}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {row.date}
                                            </TableCell>
                                            <TableCell>
                                                {statusBadge(row.status)}
                                            </TableCell>
                                            <TableCell>
                                                {row.description}
                                                {creditsNote(row) ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        {creditsNote(row)}
                                                    </p>
                                                ) : null}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {money(row.amount)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.qty}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setInvoice(row)
                                                    }
                                                >
                                                    View
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>

            <Dialog
                open={invoice !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setInvoice(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    {invoice ? (
                        <>
                            <DialogHeader>
                                <DialogTitle>Invoice {invoice.id}</DialogTitle>
                                <DialogDescription>
                                    {invoice.date} · {invoice.status}
                                </DialogDescription>
                            </DialogHeader>
                            <dl className="space-y-3 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Location
                                    </dt>
                                    <dd>{invoice.location ?? '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Description
                                    </dt>
                                    <dd className="text-right">
                                        {invoice.description}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Amount
                                    </dt>
                                    <dd className="tabular-nums">
                                        {money(invoice.amount)}
                                    </dd>
                                </div>
                                {creditsNote(invoice) ? (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Credits
                                        </dt>
                                        <dd>{creditsNote(invoice)}</dd>
                                    </div>
                                ) : null}
                                {invoice.card_brand && invoice.card_last4 ? (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Paid with
                                        </dt>
                                        <dd>
                                            {invoice.card_brand} ending{' '}
                                            {invoice.card_last4}
                                        </dd>
                                    </div>
                                ) : null}
                                {invoice.receipt_url ? (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Receipt
                                        </dt>
                                        <dd>
                                            <a
                                                href={invoice.receipt_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-brass underline-offset-2 hover:underline"
                                            >
                                                View receipt
                                            </a>
                                        </dd>
                                    </div>
                                ) : null}
                            </dl>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setInvoice(null)}
                                >
                                    Close
                                </Button>
                            </DialogFooter>
                        </>
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}

BillingIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Billing & invoices', href: billingIndex() },
    ],
};
