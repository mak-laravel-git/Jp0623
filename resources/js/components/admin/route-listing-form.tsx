import type { UrlMethodPair } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { motion } from 'motion/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface RouteListingFormProps {
    action: UrlMethodPair;
    isEdit?: boolean;
    defaults?: {
        city: string;
        about: string;
        locations_count: number;
        machines_count: number;
        annual_revenue: number;
        asking_price: number;
        seller_financing: boolean;
        is_active: boolean;
        listed_at: string;
    };
    onCancel?: () => void;
}

export function RouteListingForm({
    action,
    isEdit = false,
    defaults,
    onCancel,
}: RouteListingFormProps) {
    const form = useForm(action, {
        city: defaults?.city ?? '',
        about: defaults?.about ?? '',
        locations_count: defaults?.locations_count ?? 0,
        machines_count: defaults?.machines_count ?? 0,
        annual_revenue: defaults?.annual_revenue ?? 0,
        asking_price: defaults?.asking_price ?? 0,
        seller_financing: defaults?.seller_financing ?? false,
        is_active: defaults?.is_active ?? true,
        listed_at: defaults?.listed_at ?? new Date().toISOString().slice(0, 10),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        form.submit({
            preserveScroll: true,
            onSuccess: () => {
                if (!isEdit) {
                    form.reset();
                }
            },
        });
    };

    const numberField = (
        name:
            | 'locations_count'
            | 'machines_count'
            | 'annual_revenue'
            | 'asking_price',
        label: string,
        hint?: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                type="number"
                min={0}
                value={form.data[name]}
                onChange={(e) =>
                    form.setData(name, Number(e.target.value) || 0)
                }
                onBlur={() => form.validate(name)}
                aria-invalid={form.invalid(name)}
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <motion.form
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.25 }}
            onSubmit={handleSubmit}
            className="space-y-6"
        >
            <div className="grid gap-2 sm:max-w-md">
                <Label htmlFor="city">City</Label>
                <Input
                    id="city"
                    value={form.data.city}
                    onChange={(e) => form.setData('city', e.target.value)}
                    onBlur={() => form.validate('city')}
                    aria-invalid={form.invalid('city')}
                    placeholder="e.g. Dallas–Fort Worth, TX"
                />
                <InputError message={form.errors.city} />
            </div>

            <div className="grid gap-2 sm:max-w-md">
                <Label htmlFor="about">About</Label>
                <Input
                    id="about"
                    value={form.data.about}
                    onChange={(e) => form.setData('about', e.target.value)}
                    onBlur={() => form.validate('about')}
                    aria-invalid={form.invalid('about')}
                    placeholder="e.g. Office & light industrial"
                />
                <p className="text-xs text-muted-foreground">
                    Short summary members see in the listings table.
                </p>
                <InputError message={form.errors.about} />
            </div>

            <div className="grid gap-5 sm:grid-cols-2 sm:max-w-2xl">
                {numberField('locations_count', 'Locations')}
                {numberField('machines_count', 'Machines')}
                {numberField('annual_revenue', 'Annual revenue ($)')}
                {numberField('asking_price', 'Asking price ($)')}
            </div>

            <div className="grid gap-2 sm:max-w-xs">
                <Label htmlFor="listed_at">Listed date</Label>
                <Input
                    id="listed_at"
                    type="date"
                    value={form.data.listed_at}
                    onChange={(e) => form.setData('listed_at', e.target.value)}
                    onBlur={() => form.validate('listed_at')}
                    aria-invalid={form.invalid('listed_at')}
                />
                <InputError message={form.errors.listed_at} />
            </div>

            <div className="flex flex-wrap items-center gap-6">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="seller_financing"
                        checked={form.data.seller_financing}
                        onCheckedChange={(checked) =>
                            form.setData('seller_financing', checked === true)
                        }
                    />
                    <Label htmlFor="seller_financing" className="font-normal">
                        Seller financing available
                    </Label>
                </div>

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="is_active"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                    />
                    <Label htmlFor="is_active" className="font-normal">
                        Active (visible to members)
                    </Label>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <Button type="submit" disabled={form.processing}>
                    <Save className="h-4 w-4" />
                    {isEdit ? 'Save changes' : 'Create listing'}
                </Button>
                {onCancel && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        disabled={form.processing}
                    >
                        Cancel
                    </Button>
                )}
            </div>
        </motion.form>
    );
}
