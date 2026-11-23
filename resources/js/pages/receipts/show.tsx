import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { PaymentDetailRows } from '@/components/payment-detail-rows';
import type { DetailRow } from '@/components/payment-detail-rows';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

interface Receipt {
    number: string;
    kind: 'lead' | 'membership' | 'refund';
    title: string;
    date: string;
    status: string;
    customer: { name: string; email: string; company: string | null };
    items: { description: string; amount: number }[];
    total: number;
    credits_applied: number;
    card_paid: number;
    payment_method: string;
    details: DetailRow[];
    related: { label: string; url: string } | null;
    seller: {
        name: string;
        address: string | null;
        email: string | null;
        phone: string | null;
    };
}

function money(value: number): string {
    return `${value < 0 ? '-' : ''}$${Math.abs(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function statusStyle(status: string): string {
    if (status === 'Paid' || status === 'Completed') {
        return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200';
    }

    if (status === 'Failed') {
        return 'border-destructive/40 bg-destructive/10 text-destructive';
    }

    return 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200';
}

/**
 * Our own receipt (Square's receipt links do not open for sandbox payments). Print-friendly: the
 * Print / Save as PDF button uses the browser's print dialog and the app shell is hidden when printing.
 */
export default function ReceiptShow({
    receipt,
    backUrl,
}: {
    receipt: Receipt;
    backUrl: string;
}) {
    const isRefund = receipt.kind === 'refund';
    const paidWithLabel = isRefund ? 'Refunded to your card' : 'Paid by card';
    const creditsLabel = isRefund ? 'Returned as Credits' : 'Credits used';

    return (
        <>
            <Head title={`Receipt ${receipt.number}`} />

            <div className="w-full px-4 py-6 sm:px-6 lg:px-8 print:p-0">
                <div className="mx-auto mb-4 flex max-w-3xl flex-col gap-2 sm:flex-row sm:justify-between print:hidden">
                    <Button variant="outline" asChild className="sm:w-auto">
                        <Link href={backUrl}>
                            <ArrowLeft className="h-4 w-4" /> Back
                        </Link>
                    </Button>
                    <Button
                        type="button"
                        onClick={() => window.print()}
                        className="sm:w-auto"
                    >
                        <Printer className="h-4 w-4" /> Print / Save as PDF
                    </Button>
                </div>

                <article className="mx-auto max-w-3xl space-y-6 rounded-xl border bg-card p-5 shadow-sm sm:p-8 print:max-w-none print:border-0 print:p-0 print:shadow-none">
                    <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <p className="text-xl font-extrabold tracking-tight">
                                {receipt.seller.name}
                            </p>
                            {receipt.seller.address ? (
                                <p className="mt-1 text-sm whitespace-pre-line text-muted-foreground">
                                    {receipt.seller.address}
                                </p>
                            ) : null}
                            {receipt.seller.email ? (
                                <p className="text-sm text-muted-foreground">
                                    {receipt.seller.email}
                                </p>
                            ) : null}
                            {receipt.seller.phone ? (
                                <p className="text-sm text-muted-foreground">
                                    {receipt.seller.phone}
                                </p>
                            ) : null}
                        </div>
                        <div className="sm:text-right">
                            <p className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                {receipt.title}
                            </p>
                            <p className="font-mono text-lg font-bold">
                                {receipt.number}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {receipt.date}
                            </p>
                            <Badge
                                variant="outline"
                                className={`mt-2 ${statusStyle(receipt.status)}`}
                            >
                                {receipt.status}
                            </Badge>
                        </div>
                    </header>

                    <section>
                        <h2 className="mb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Billed to
                        </h2>
                        <p className="font-medium">{receipt.customer.name}</p>
                        {receipt.customer.company ? (
                            <p className="text-sm text-muted-foreground">
                                {receipt.customer.company}
                            </p>
                        ) : null}
                        <p className="text-sm text-muted-foreground">
                            {receipt.customer.email}
                        </p>
                    </section>

                    <section>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2 font-medium">
                                            Description
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {receipt.items.map((item) => (
                                        <tr
                                            key={item.description}
                                            className="border-b"
                                        >
                                            <td className="py-3 pr-4">
                                                {item.description}
                                            </td>
                                            <td className="py-3 text-right tabular-nums">
                                                {money(item.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <dl className="mt-4 ml-auto w-full space-y-2 text-sm sm:max-w-xs">
                            <div className="flex justify-between gap-4 font-semibold">
                                <dt>Total</dt>
                                <dd className="tabular-nums">
                                    {money(receipt.total)}
                                </dd>
                            </div>
                            {receipt.credits_applied > 0 ? (
                                <div className="flex justify-between gap-4 text-muted-foreground">
                                    <dt>{creditsLabel}</dt>
                                    <dd className="tabular-nums">
                                        {receipt.credits_applied} Credits
                                    </dd>
                                </div>
                            ) : null}
                            {receipt.card_paid > 0 ? (
                                <div className="flex justify-between gap-4 text-muted-foreground">
                                    <dt>{paidWithLabel}</dt>
                                    <dd className="tabular-nums">
                                        {money(receipt.card_paid)}
                                    </dd>
                                </div>
                            ) : null}
                        </dl>
                    </section>

                    <section className="border-t pt-5">
                        <h2 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Payment details
                        </h2>
                        <PaymentDetailRows rows={receipt.details} />
                    </section>

                    {receipt.related ? (
                        <p className="text-sm print:hidden">
                            <Link
                                href={receipt.related.url}
                                className="text-brass underline-offset-2 hover:underline"
                            >
                                {receipt.related.label}
                            </Link>
                        </p>
                    ) : null}
                </article>
            </div>
        </>
    );
}

ReceiptShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Receipt' },
    ],
};
