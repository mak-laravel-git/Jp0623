import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { updateLocation } from '@/actions/App/Http/Controllers/Operator/AccountController';
import { LocationAutocomplete } from '@/components/location/location-autocomplete';
import type { LocationSuggestion } from '@/components/location/location-autocomplete';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { location as accountLocation } from '@/routes/account';

type AccountLocationProps = {
    primaryCity: string;
    location: LocationSuggestion | null;
    additionalServiceAreas: string;
    changesRemaining: number;
    changesTotal: number;
    changeWindowDays: number;
};

export default function AccountLocation({
    location,
    additionalServiceAreas,
    changesRemaining,
    changesTotal,
    changeWindowDays,
}: AccountLocationProps) {
    const [query, setQuery] = useState(location?.label ?? '');
    const [selected, setSelected] = useState<LocationSuggestion | null>(
        location,
    );
    const form = useForm(updateLocation(), {
        city: location?.city ?? '',
        state: location?.state ?? '',
        address: location?.address ?? '',
        postal_code: location?.postal_code ?? '',
        latitude: location?.latitude ?? null,
        longitude: location?.longitude ?? null,
        additional_service_areas: additionalServiceAreas,
    });

    function handleSelect(suggestion: LocationSuggestion) {
        setSelected(suggestion);
        setQuery(suggestion.label);
        form.clearErrors('city', 'state', 'latitude', 'longitude');
        form.setData((data) => ({
            ...data,
            city: suggestion.city,
            state: suggestion.state,
            address: suggestion.address ?? '',
            postal_code: suggestion.postal_code ?? '',
            latitude: suggestion.latitude,
            longitude: suggestion.longitude,
        }));
    }

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit({ preserveScroll: true });
    }

    return (
        <>
            <Head title="Location settings" />

            <div className="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-3xl font-extrabold tracking-tight">
                        Location settings
                    </h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        You get lead alerts within 50 miles of your primary
                        city.
                    </p>
                </div>

                <div className="rounded-xl border bg-card p-6 shadow-sm">
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <LocationAutocomplete
                            id="primary-city"
                            label="Primary city I offer vending in"
                            value={query}
                            selected={selected}
                            suggestType="city"
                            onQueryChange={setQuery}
                            onSelect={handleSelect}
                            onClearSelection={() => setSelected(null)}
                            error={
                                form.errors.city ??
                                form.errors.state ??
                                form.errors.latitude ??
                                form.errors.longitude
                            }
                        />
                        <p className="text-sm text-muted-foreground">
                            You may change your primary city up to{' '}
                            {changesTotal} times in {changeWindowDays} days.
                            Changes remaining:{' '}
                            <strong>
                                {changesRemaining} of {changesTotal}
                            </strong>
                            .
                        </p>
                        <div className="space-y-2">
                            <Label htmlFor="addon-areas">
                                Additional service areas (Multi-market add-on)
                            </Label>
                            <Input
                                id="addon-areas"
                                placeholder="e.g. Austin, TX"
                                value={form.data.additional_service_areas}
                                onChange={(event) =>
                                    form.setData(
                                        'additional_service_areas',
                                        event.target.value,
                                    )
                                }
                            />
                            <p className="text-sm text-muted-foreground">
                                List other markets you serve, separated by
                                commas.
                            </p>
                            {form.errors.additional_service_areas ? (
                                <p className="text-sm text-red-600">
                                    {form.errors.additional_service_areas}
                                </p>
                            ) : null}
                        </div>
                        <Button
                            type="submit"
                            disabled={form.processing || selected === null}
                            className="bg-brass text-brass-ink hover:bg-brass/90"
                        >
                            {form.processing ? 'Saving…' : 'Save'}
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}

AccountLocation.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Location settings', href: accountLocation() },
    ],
};
