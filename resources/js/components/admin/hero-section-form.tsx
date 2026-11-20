import type { UrlMethodPair } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Save, Trash2 } from 'lucide-react';
import FileUpload from '@/components/file-upload';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { AdminHeroSection } from '@/types/admin';

const MAX_BULLETS = 8;
const MAX_IMAGES = 10;

type ImageItem = {
    id: number | null;
    alt: string;
    image: File | null;
    url: string | null;
};

function move<T>(items: T[], index: number, direction: -1 | 1): T[] {
    const target = index + direction;

    if (target < 0 || target >= items.length) {
        return items;
    }

    const next = [...items];
    [next[index], next[target]] = [next[target], next[index]];

    return next;
}

export function HeroSectionForm({
    action,
    heroSection,
}: {
    action: UrlMethodPair;
    heroSection: AdminHeroSection;
}) {
    const form = useForm(action, {
        eyebrow: heroSection.eyebrow,
        title_before: heroSection.title_before,
        title_accent: heroSection.title_accent,
        lede: heroSection.lede,
        bullets: heroSection.bullets as string[],
        cta_label: heroSection.cta_label,
        cta_href: heroSection.cta_href,
        trust_stars: heroSection.trust_stars,
        trust_text: heroSection.trust_text,
        trust_reviews_label: heroSection.trust_reviews_label,
        trust_reviews_href: heroSection.trust_reviews_href,
        images: heroSection.images.map(
            (image): ImageItem => ({
                id: image.id,
                alt: image.alt,
                image: null,
                url: image.image_url,
            }),
        ),
    });

    const errors = form.errors as Record<string, string | undefined>;

    const setBullets = (bullets: string[]) => form.setData('bullets', bullets);
    const setImages = (images: ImageItem[]) => form.setData('images', images);

    const updateImage = (index: number, patch: Partial<ImageItem>) =>
        setImages(
            form.data.images.map((item, i) =>
                i === index ? { ...item, ...patch } : item,
            ),
        );

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        // Pictures are nested in the payload, so the request is always multipart.
        form.submit({ forceFormData: true, preserveScroll: true });
    };

    const textField = (
        name: 'eyebrow' | 'title_before' | 'title_accent',
        label: string,
        hint?: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                aria-invalid={!!errors[name]}
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={errors[name]} />
        </div>
    );

    return (
        <form onSubmit={handleSubmit} className="space-y-8">
            <section className="space-y-4">
                <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                    Heading
                </h2>
                <div className="grid gap-4">
                    {textField('eyebrow', 'Eyebrow (small line above)')}
                    {textField('title_before', 'Heading')}
                    {textField(
                        'title_accent',
                        'Highlighted heading',
                        'Shown in the accent color right after the heading.',
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="lede">Description</Label>
                        <Textarea
                            id="lede"
                            rows={3}
                            value={form.data.lede}
                            onChange={(e) =>
                                form.setData('lede', e.target.value)
                            }
                            aria-invalid={!!errors.lede}
                        />
                        <InputError message={errors.lede} />
                    </div>
                </div>
            </section>

            <section className="space-y-4">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                        Bullet points
                    </h2>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={form.data.bullets.length >= MAX_BULLETS}
                        onClick={() => setBullets([...form.data.bullets, ''])}
                    >
                        <Plus className="h-4 w-4" /> Add bullet
                    </Button>
                </div>
                <InputError message={errors.bullets} />
                <div className="space-y-3">
                    {form.data.bullets.map((bullet, index) => (
                        <div key={index} className="space-y-1">
                            <div className="flex items-center gap-2">
                                <Input
                                    aria-label={`Bullet point ${index + 1}`}
                                    value={bullet}
                                    onChange={(e) =>
                                        setBullets(
                                            form.data.bullets.map((b, i) =>
                                                i === index ? e.target.value : b,
                                            ),
                                        )
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Move up"
                                    disabled={index === 0}
                                    onClick={() =>
                                        setBullets(
                                            move(form.data.bullets, index, -1),
                                        )
                                    }
                                >
                                    <ArrowUp className="h-4 w-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Move down"
                                    disabled={
                                        index === form.data.bullets.length - 1
                                    }
                                    onClick={() =>
                                        setBullets(
                                            move(form.data.bullets, index, 1),
                                        )
                                    }
                                >
                                    <ArrowDown className="h-4 w-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Remove bullet"
                                    className="text-muted-foreground hover:text-destructive"
                                    disabled={form.data.bullets.length <= 1}
                                    onClick={() =>
                                        setBullets(
                                            form.data.bullets.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                            <InputError message={errors[`bullets.${index}`]} />
                        </div>
                    ))}
                </div>
            </section>

            <section className="space-y-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Pictures
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Shown in this order and cross-fading on the home
                            page. With fewer than 3 pictures they stay still.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={form.data.images.length >= MAX_IMAGES}
                        onClick={() =>
                            setImages([
                                ...form.data.images,
                                { id: null, alt: '', image: null, url: null },
                            ])
                        }
                    >
                        <Plus className="h-4 w-4" /> Add picture
                    </Button>
                </div>
                <InputError message={errors.images} />

                <div className="space-y-4">
                    {form.data.images.map((item, index) => (
                        <div
                            key={item.id ?? `new-${index}`}
                            className="space-y-3 rounded-lg border bg-muted/20 p-4"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-medium">
                                    Picture {index + 1}
                                </p>
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Move picture up"
                                        disabled={index === 0}
                                        onClick={() =>
                                            setImages(
                                                move(form.data.images, index, -1),
                                            )
                                        }
                                    >
                                        <ArrowUp className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Move picture down"
                                        disabled={
                                            index ===
                                            form.data.images.length - 1
                                        }
                                        onClick={() =>
                                            setImages(
                                                move(form.data.images, index, 1),
                                            )
                                        }
                                    >
                                        <ArrowDown className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remove picture"
                                        className="text-muted-foreground hover:text-destructive"
                                        disabled={form.data.images.length <= 1}
                                        onClick={() =>
                                            setImages(
                                                form.data.images.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-[14rem_1fr]">
                                {item.url && !item.image ? (
                                    <div className="grid h-40 place-items-center overflow-hidden rounded-md border bg-background p-2">
                                        <img
                                            src={item.url}
                                            alt={item.alt}
                                            className="max-h-full max-w-full object-contain"
                                        />
                                    </div>
                                ) : (
                                    <div className="grid h-40 place-items-center rounded-md border border-dashed text-xs text-muted-foreground">
                                        {item.image
                                            ? 'New picture selected'
                                            : 'No picture yet'}
                                    </div>
                                )}

                                <div className="space-y-3">
                                    <div className="grid gap-2">
                                        <Label>
                                            {item.id
                                                ? 'Replace picture'
                                                : 'Picture'}
                                        </Label>
                                        <FileUpload
                                            accept="image/*"
                                            maxSize={4}
                                            value={item.image}
                                            onChange={(file) =>
                                                updateImage(index, {
                                                    image:
                                                        (file as File | null) ??
                                                        null,
                                                })
                                            }
                                            placeholder="Drag & drop a picture, or click to browse"
                                            hint="PNG, JPG or WEBP up to 4MB"
                                            error={errors[`images.${index}.image`]}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor={`image-alt-${index}`}>
                                            Description (alt text)
                                        </Label>
                                        <Input
                                            id={`image-alt-${index}`}
                                            value={item.alt}
                                            onChange={(e) =>
                                                updateImage(index, {
                                                    alt: e.target.value,
                                                })
                                            }
                                            aria-invalid={
                                                !!errors[`images.${index}.alt`]
                                            }
                                            placeholder="e.g. Sandstar AI cooler"
                                        />
                                        <InputError
                                            message={
                                                errors[`images.${index}.alt`]
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            <Button type="submit" disabled={form.processing}>
                <Save className="h-4 w-4" />
                {form.processing ? 'Saving…' : 'Save hero section'}
            </Button>
        </form>
    );
}
