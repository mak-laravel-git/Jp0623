import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store as storeLeadIssue } from '@/routes/leads/purchases/issue';
import type { PurchasedLead } from '@/types/operator-dashboard';

const ISSUE_CATEGORIES = [
    { value: 'fake_lead', label: 'Fake or wrong lead' },
    { value: 'cannot_serve', label: 'I could not provide the service' },
    { value: 'other', label: 'Other' },
] as const;

interface PurchasedLeadIssueDialogProps {
    lead: PurchasedLead | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * Sends a purchased lead back to the admin. The written reason is required; the admin reviews it.
 */
export function PurchasedLeadIssueDialog({
    lead,
    open,
    onOpenChange,
}: PurchasedLeadIssueDialogProps) {
    if (!lead) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <IssueForm
                    key={lead.purchase_id}
                    lead={lead}
                    onClose={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function IssueForm({
    lead,
    onClose,
}: {
    lead: PurchasedLead;
    onClose: () => void;
}) {
    const form = useForm(storeLeadIssue(lead.purchase_id), {
        category: ISSUE_CATEGORIES[0].value as string,
        reason: '',
    });
    const [confirmed, setConfirmed] = useState(false);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (form.processing || !confirmed) {
            return;
        }

        form.submit({
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-5">
            <DialogHeader>
                <DialogTitle>Return lead {lead.id}</DialogTitle>
                <DialogDescription>
                    {lead.name ?? lead.id} · {lead.type}. Tell the admin why
                    you are sending this lead back. Misleading requests may
                    lead to account suspension.
                </DialogDescription>
            </DialogHeader>

            <fieldset className="space-y-3">
                <legend className="text-sm font-medium">
                    What is the problem?
                </legend>
                <div className="space-y-2">
                    {ISSUE_CATEGORIES.map((option) => (
                        <label
                            key={option.value}
                            className="flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2 text-sm has-checked:border-brass has-checked:bg-brass/5"
                        >
                            <input
                                type="radio"
                                name="issue-category"
                                value={option.value}
                                checked={form.data.category === option.value}
                                onChange={() =>
                                    form.setData('category', option.value)
                                }
                                className="mt-0.5 size-4 accent-brass"
                            />
                            <span>{option.label}</span>
                        </label>
                    ))}
                </div>
                <InputError message={form.errors.category} />
            </fieldset>

            <div className="space-y-2">
                <Label htmlFor="issue-reason">
                    Why are you returning this lead?
                </Label>
                <Textarea
                    id="issue-reason"
                    rows={4}
                    required
                    minLength={10}
                    maxLength={2000}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    placeholder="Describe what went wrong (at least 10 characters)…"
                    aria-invalid={!!form.errors.reason}
                />
                <InputError message={form.errors.reason} />
            </div>

            <label className="flex items-start gap-3 text-sm">
                <Checkbox
                    checked={confirmed}
                    onCheckedChange={(value) => setConfirmed(value === true)}
                    className="mt-0.5"
                />
                <span>
                    I understand the admin will verify this request with the
                    location.
                </span>
            </label>

            <DialogFooter className="gap-2 sm:gap-0">
                <Button type="button" variant="outline" onClick={onClose}>
                    Cancel
                </Button>
                <Button
                    type="submit"
                    disabled={form.processing || !confirmed}
                    className="bg-brass text-brass-ink hover:bg-brass/90"
                >
                    {form.processing ? 'Sending…' : 'Send to admin'}
                </Button>
            </DialogFooter>
        </form>
    );
}

