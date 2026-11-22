import { Head, router, useForm } from '@inertiajs/react';
import { CreditCard, Save, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { ConfirmDeleteDialog } from '@/components/admin/confirm-delete-dialog';
import { DataPagination } from '@/components/admin/data-pagination';
import { MembershipCustomPriceDialog } from '@/components/admin/membership-custom-price-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { dashboard } from '@/routes';
import membershipSettings from '@/routes/admin/membership-settings';
import { destroy as destroyCustomPrice } from '@/routes/admin/membership-settings/custom-prices';
import type { AdminMembershipCustomPrice, Paginated } from '@/types/admin';

const MEMBERSHIP_STATUS_LABELS: Record<string, string> = {
    active_free: 'Free',
    active: 'Active (paid)',
    required: 'Payment required',
    past_due: 'Past due',
    canceled: 'Canceled',
};

type MembershipSettingsEditProps = {
    customPrices: Paginated<AdminMembershipCustomPrice>;
    settings: {
        mode: 'free' | 'paid';
        monthly_price: number;
        grace_days: number;
    };
    counts: {
        required: number;
        past_due: number;
        active: number;
        active_free: number;
    };
};

export default function EditMembershipSettings({
    customPrices,
    settings,
    counts,
}: MembershipSettingsEditProps) {
    const [customPriceOpen, setCustomPriceOpen] = useState(false);
    const form = useForm(membershipSettings.update(), {
        mode: settings.mode,
        monthly_price: settings.monthly_price,
        grace_days: settings.grace_days,
    });

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        form.submit({ preserveScroll: true });
    }

    return (
        <>
            <Head title="Membership settings" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <AdminPageHeader
                    title="Membership settings"
                    description="Control whether operator membership is free or billed monthly, and set a custom price for individual members."
                    icon={CreditCard}
                >
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setCustomPriceOpen(true)}
                    >
                        <UserPlus className="h-4 w-4" /> Custom member pricing
                    </Button>
                </AdminPageHeader>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat label="Active free" value={counts.active_free} />
                    <Stat label="Active paid" value={counts.active} />
                    <Stat label="Required" value={counts.required} />
                    <Stat label="Past due" value={counts.past_due} />
                </div>

                <div className="rounded-xl border bg-card p-6 shadow-sm">
                    <form onSubmit={handleSubmit} className="max-w-lg space-y-6">
                        <div className="grid gap-2">
                            <Label>Membership mode</Label>
                            <Select
                                value={form.data.mode}
                                onValueChange={(value: 'free' | 'paid') =>
                                    form.setData('mode', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="free">Free</SelectItem>
                                    <SelectItem value="paid">
                                        Paid monthly
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.mode} />
                            <p className="text-xs text-muted-foreground">
                                Switching to paid requires every operator to
                                subscribe. Switching to free stops new charges.
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="monthly_price">
                                Monthly price (USD)
                            </Label>
                            <Input
                                id="monthly_price"
                                type="number"
                                min={1}
                                value={form.data.monthly_price}
                                onChange={(e) =>
                                    form.setData(
                                        'monthly_price',
                                        Number(e.target.value) || 0,
                                    )
                                }
                            />
                            <InputError message={form.errors.monthly_price} />
                        </div>

                        <Button type="submit" disabled={form.processing}>
                            <Save className="h-4 w-4" />
                            {form.processing ? 'Saving…' : 'Save settings'}
                        </Button>
                    </form>
                </div>

                <div className="rounded-xl border bg-card shadow-sm">
                    <div className="border-b p-5">
                        <h2 className="text-lg font-semibold">
                            Custom member prices
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            These members pay their own monthly price instead of
                            the default price, in any membership mode.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/50 hover:bg-muted/50">
                                    <TableHead>Email</TableHead>
                                    <TableHead>Member</TableHead>
                                    <TableHead className="text-right">
                                        Price
                                    </TableHead>
                                    <TableHead>Membership</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {customPrices.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">
                                            {row.email}
                                        </TableCell>
                                        <TableCell>
                                            {row.registered
                                                ? row.member_name
                                                : 'Not registered yet'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            ${row.monthly_price}/mo
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    row.registered
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {row.registered
                                                    ? (MEMBERSHIP_STATUS_LABELS[
                                                          row.membership_status ??
                                                              ''
                                                      ] ?? 'Custom price')
                                                    : 'Reserved'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <ConfirmDeleteDialog
                                                description={
                                                    <>
                                                        Remove the custom price
                                                        for{' '}
                                                        <strong>
                                                            {row.email}
                                                        </strong>
                                                        ? They will use the
                                                        default membership
                                                        again.
                                                    </>
                                                }
                                                onConfirm={() =>
                                                    router.delete(
                                                        destroyCustomPrice(
                                                            row.id,
                                                        ).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-muted-foreground hover:text-destructive"
                                                    aria-label="Remove custom price"
                                                    title="Remove"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </ConfirmDeleteDialog>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {customPrices.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            No custom prices yet. Use “Custom
                                            member pricing” to set one.
                                        </TableCell>
                                    </TableRow>
                                ) : null}
                            </TableBody>
                        </Table>
                    </div>
                    <DataPagination meta={customPrices} />
                </div>
            </div>

            <MembershipCustomPriceDialog
                open={customPriceOpen}
                onOpenChange={setCustomPriceOpen}
            />
        </>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border bg-card p-4 shadow-sm">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-semibold">{value}</p>
        </div>
    );
}

EditMembershipSettings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Membership settings', href: membershipSettings.edit() },
    ],
};
