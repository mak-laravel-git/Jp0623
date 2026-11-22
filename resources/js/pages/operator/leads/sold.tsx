import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDistance, leadArea } from '@/lib/lead';
import { dashboard } from '@/routes';
import { location as accountLocation } from '@/routes/account';
import { sold as leadsSold } from '@/routes/leads';
import type { OperatorSoldLead } from '@/types/operator-dashboard';

function money(value: number): string {
    return `$${value.toLocaleString('en-US')}`;
}

export default function LeadsSold({
    soldLeads,
    primaryCity,
    hasLocation,
    radius,
}: {
    soldLeads: OperatorSoldLead[];
    primaryCity: string;
    hasLocation: boolean;
    radius: number;
}) {
    return (
        <>
            <Head title="Sold leads near you" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        Sold leads near you
                    </h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        Recently sold leads within {radius} miles of{' '}
                        {primaryCity}, newest first. Business and contact
                        details stay private to the operator who bought the
                        lead.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/50 hover:bg-muted/50">
                                    <TableHead>Sold</TableHead>
                                    <TableHead>Location</TableHead>
                                    <TableHead>City / ZIP</TableHead>
                                    <TableHead className="text-right">
                                        Distance
                                    </TableHead>
                                    <TableHead className="text-right">
                                        People
                                    </TableHead>
                                    <TableHead>Wants</TableHead>
                                    <TableHead className="text-right">
                                        Price
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {soldLeads.map((lead) => (
                                    <TableRow key={lead.id}>
                                        <TableCell className="font-mono text-xs">
                                            {lead.sold}
                                        </TableCell>
                                        <TableCell>
                                            <p className="font-medium">
                                                {lead.type}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {lead.id}
                                            </p>
                                        </TableCell>
                                        <TableCell>{leadArea(lead)}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatDistance(lead.dist)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {lead.people ?? '—'}
                                        </TableCell>
                                        <TableCell>{lead.wants || '—'}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {money(lead.price)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                <Badge variant="secondary">
                                                    Sold
                                                </Badge>
                                                {lead.mine ? (
                                                    <Badge variant="outline">
                                                        You
                                                    </Badge>
                                                ) : null}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {soldLeads.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            {hasLocation ? (
                                                <>
                                                    No leads sold within{' '}
                                                    {radius} miles yet. They
                                                    appear here once an
                                                    operator buys one.
                                                </>
                                            ) : (
                                                <>
                                                    Set your location to see
                                                    leads sold near you.{' '}
                                                    <Link
                                                        href={accountLocation()}
                                                        className="font-medium text-petrol underline-offset-4 hover:underline dark:text-brass"
                                                    >
                                                        Set location
                                                    </Link>
                                                </>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ) : null}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <Button variant="outline" asChild>
                    <Link href={dashboard()}>Back to dashboard</Link>
                </Button>
            </div>
        </>
    );
}

LeadsSold.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sold leads near you', href: leadsSold() },
    ],
};
