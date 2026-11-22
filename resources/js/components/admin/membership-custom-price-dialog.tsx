import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { lookup, store } from '@/routes/admin/membership-settings/custom-prices';
import type { MembershipCustomPriceLookup } from '@/types/admin';

/**
 * Sets a custom monthly price for one member, by email. Works for an email that has no account yet.
 */
export function MembershipCustomPriceDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm(store(), {
        email: '',
        monthly_price: '' as string | number,
        send_email: true,
    });
    const [found, setFound] = useState<MembershipCustomPriceLookup | null>(
        null,
    );
    const [checking, setChecking] = useState(false);
    const [lookupError, setLookupError] = useState<string | null>(null);

    function reset() {
        form.reset();
        form.clearErrors();
        setFound(null);
        setLookupError(null);
    }

    function handleOpenChange(next: boolean) {
        if (!next) {
            reset();
        }

        onOpenChange(next);
    }

    async function checkEmail() {
        const email = form.data.email.trim();

        if (email === '') {
            return;
        }

        setChecking(true);
        setLookupError(null);

        try {
            const response = await fetch(lookup({ query: { email } }).url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Enter a valid email address.');
            }

            setFound((await response.json()) as MembershipCustomPriceLookup);
        } catch (error) {
            setFound(null);
            setLookupError(
                error instanceof Error ? error.message : 'Could not check.',
            );
        } finally {
            setChecking(false);
        }
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.submit({
            preserveScroll: true,
            onSuccess: () => handleOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Custom member pricing</DialogTitle>
                        <DialogDescription>
                            Set a monthly price for one person. They pay this
                            instead of the default price, whatever the
                            membership mode is. The email does not need to have
                            an account yet.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="custom-price-email">Member email</Label>
                        <div className="flex gap-2">
                            <Input
                                id="custom-price-email"
                                type="email"
                                autoComplete="off"
                                placeholder="member@example.com"
                                value={form.data.email}
                                onChange={(event) => {
                                    form.setData('email', event.target.value);
                                    setFound(null);
                                    setLookupError(null);
                                }}
                                onBlur={() => void checkEmail()}
                                aria-invalid={!!form.errors.email}
                                className="min-w-0 flex-1"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                disabled={checking || form.data.email === ''}
                                onClick={() => void checkEmail()}
                            >
                                {checking ? 'Checking…' : 'Check'}
                            </Button>
                        </div>
                        <InputError message={form.errors.email} />
                        {lookupError ? (
                            <p className="text-sm text-destructive">
                                {lookupError}
                            </p>
                        ) : null}
                        {found ? (
                            <p
                                role="status"
                                className={
                                    found.exists
                                        ? 'rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-950 dark:text-amber-100'
                                        : 'rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground'
                                }
                            >
                                {found.message}
                            </p>
                        ) : null}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="custom-price-amount">
                            Monthly price (USD)
                        </Label>
                        <Input
                            id="custom-price-amount"
                            type="number"
                            min={1}
                            value={form.data.monthly_price}
                            onChange={(event) =>
                                form.setData('monthly_price', event.target.value)
                            }
                            aria-invalid={!!form.errors.monthly_price}
                            className="sm:max-w-40"
                        />
                        <InputError message={form.errors.monthly_price} />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="custom-price-email-member"
                            checked={form.data.send_email}
                            onCheckedChange={(checked) =>
                                form.setData('send_email', checked === true)
                            }
                        />
                        <Label
                            htmlFor="custom-price-email-member"
                            className="font-normal"
                        >
                            Email the member about this price
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save price'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
