import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { index as routesIndex } from '@/routes/routes';
import { store as storeInquiry } from '@/routes/routes/inquiries';

interface RouteListing {
    listing_id: number;
    id: string;
    city: string;
    about: string;
    locs: number;
    machines: number;
    rev: number;
    ask: number;
    fin: string;
    listed: string;
}

interface MemberContact {
    name: string;
    email: string;
    phone: string;
}

const DEFAULT_MESSAGE =
    "I'm interested in this route. Please send more details.";

function money(value: number): string {
    return `$${value.toLocaleString('en-US')}`;
}

export default function RoutesIndex({
    routes,
    memberContact,
}: {
    routes: RouteListing[];
    memberContact: MemberContact;
}) {
    const [query, setQuery] = useState('');
    const [maxAsk, setMaxAsk] = useState('99999999');
    const [selected, setSelected] = useState<RouteListing | null>(null);
    const [inquiryFor, setInquiryFor] = useState<RouteListing | null>(null);

    const filtered = useMemo(() => {
        const max = Number(maxAsk);
        const q = query.trim().toLowerCase();

        return routes.filter((route) => {
            if (route.ask > max) {
                return false;
            }

            if (!q) {
                return true;
            }

            return (
                route.city.toLowerCase().includes(q) ||
                route.about.toLowerCase().includes(q) ||
                route.id.toLowerCase().includes(q)
            );
        });
    }, [routes, query, maxAsk]);

    return (
        <>
            <Head title="Established locations & routes for sale" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        Established locations & routes for sale
                    </h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        Established vending locations and routes currently
                        available for purchase.
                    </p>
                </div>

                <div className="flex flex-wrap gap-4 rounded-xl border bg-card p-4 shadow-sm">
                    <div className="min-w-[200px] flex-1 space-y-2">
                        <Label htmlFor="route-search">Search</Label>
                        <Input
                            id="route-search"
                            placeholder="City or route type"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                        />
                    </div>
                    <div className="min-w-[180px] space-y-2">
                        <Label>Max asking price</Label>
                        <Select value={maxAsk} onValueChange={setMaxAsk}>
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="99999999">Any</SelectItem>
                                <SelectItem value="100000">
                                    Under $100k
                                </SelectItem>
                                <SelectItem value="150000">
                                    Under $150k
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/50 hover:bg-muted/50">
                                    <TableHead>Listed</TableHead>
                                    <TableHead>Route located in</TableHead>
                                    <TableHead>About</TableHead>
                                    <TableHead className="text-right">
                                        Locations
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Annual revenue
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Asking
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered.map((route) => (
                                    <TableRow key={route.id}>
                                        <TableCell className="font-mono text-xs">
                                            {route.listed}
                                        </TableCell>
                                        <TableCell>{route.city}</TableCell>
                                        <TableCell>{route.about}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {route.locs}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {money(route.rev)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {money(route.ask)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setSelected(route)
                                                }
                                            >
                                                Details
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>

            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    {selected ? (
                        <>
                            <DialogHeader>
                                <DialogTitle>{selected.about}</DialogTitle>
                                <DialogDescription>
                                    {selected.id} · {selected.city} · listed{' '}
                                    {selected.listed}
                                </DialogDescription>
                            </DialogHeader>

                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <Detail
                                    label="Locations"
                                    value={String(selected.locs)}
                                />
                                <Detail
                                    label="Machines"
                                    value={String(selected.machines)}
                                />
                                <Detail
                                    label="Annual revenue"
                                    value={money(selected.rev)}
                                />
                                <Detail
                                    label="Asking price"
                                    value={money(selected.ask)}
                                />
                                <Detail
                                    label="Seller financing"
                                    value={selected.fin}
                                />
                                <Detail label="Listed by" value="Route broker" />
                            </dl>

                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setSelected(null)}
                                >
                                    Close
                                </Button>
                                <Button
                                    className="bg-brass text-brass-ink hover:bg-brass/90"
                                    onClick={() => {
                                        setInquiryFor(selected);
                                        setSelected(null);
                                    }}
                                >
                                    Send inquiry
                                </Button>
                            </DialogFooter>
                        </>
                    ) : null}
                </DialogContent>
            </Dialog>

            {inquiryFor ? (
                <InquiryDialog
                    listing={inquiryFor}
                    contact={memberContact}
                    onClose={() => setInquiryFor(null)}
                />
            ) : null}
        </>
    );
}

function InquiryDialog({
    listing,
    contact,
    onClose,
}: {
    listing: RouteListing;
    contact: MemberContact;
    onClose: () => void;
}) {
    const form = useForm(storeInquiry(listing.listing_id), {
        contact_name: contact.name,
        contact_email: contact.email,
        contact_phone: contact.phone,
        message: DEFAULT_MESSAGE,
    });

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.submit({
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) {
                    onClose();
                }
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Send inquiry</DialogTitle>
                        <DialogDescription>
                            Review your contact details and write a message. We
                            will get back to you about this listing.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="inquiry-listing-id">
                                Listing ID
                            </Label>
                            <Input
                                id="inquiry-listing-id"
                                value={listing.id}
                                readOnly
                                className="bg-muted/50"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="inquiry-listing-city">City</Label>
                            <Input
                                id="inquiry-listing-city"
                                value={listing.city}
                                readOnly
                                className="bg-muted/50"
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="inquiry-name">Your name</Label>
                        <Input
                            id="inquiry-name"
                            autoComplete="name"
                            value={form.data.contact_name}
                            onChange={(event) =>
                                form.setData('contact_name', event.target.value)
                            }
                            aria-invalid={!!form.errors.contact_name}
                        />
                        <InputError message={form.errors.contact_name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="inquiry-email">Your email</Label>
                            <Input
                                id="inquiry-email"
                                type="email"
                                autoComplete="email"
                                value={form.data.contact_email}
                                onChange={(event) =>
                                    form.setData(
                                        'contact_email',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={!!form.errors.contact_email}
                            />
                            <InputError message={form.errors.contact_email} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="inquiry-phone">Your phone</Label>
                            <Input
                                id="inquiry-phone"
                                type="tel"
                                autoComplete="tel"
                                value={form.data.contact_phone}
                                onChange={(event) =>
                                    form.setData(
                                        'contact_phone',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={!!form.errors.contact_phone}
                            />
                            <InputError message={form.errors.contact_phone} />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="inquiry-message">Message</Label>
                        <Textarea
                            id="inquiry-message"
                            rows={4}
                            value={form.data.message}
                            onChange={(event) =>
                                form.setData('message', event.target.value)
                            }
                            aria-invalid={!!form.errors.message}
                        />
                        <InputError message={form.errors.message} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            className="bg-brass text-brass-ink hover:bg-brass/90"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Sending…' : 'Send inquiry'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1">{value}</dd>
        </div>
    );
}

RoutesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Established locations & routes for sale',
            href: routesIndex(),
        },
    ],
};
