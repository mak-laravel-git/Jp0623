<?php

namespace App\Http\Requests\HeroSection;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHeroSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $bullets = $this->input('bullets');

        if (is_array($bullets)) {
            $this->merge([
                'bullets' => array_values(array_filter(
                    $bullets,
                    fn (mixed $bullet): bool => trim((string) $bullet) !== '',
                )),
            ]);
        }

        $images = $this->input('images');

        if (! is_array($images)) {
            return;
        }

        foreach ($images as $index => $item) {
            if (is_array($item) && ! $this->hasFile("images.{$index}.image") && ($item['image'] ?? null) === '') {
                $images[$index]['image'] = null;
            }
        }

        $this->merge(['images' => $images]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'eyebrow' => ['required', 'string', 'max:255'],
            'title_before' => ['required', 'string', 'max:255'],
            'title_accent' => ['required', 'string', 'max:255'],
            'lede' => ['required', 'string', 'max:1000'],
            'bullets' => ['required', 'array', 'min:1', 'max:8'],
            'bullets.*' => ['required', 'string', 'max:255'],
            'cta_label' => ['required', 'string', 'max:255'],
            'cta_href' => ['required', 'string', 'max:255'],
            'trust_stars' => ['required', 'string', 'max:50'],
            'trust_text' => ['required', 'string', 'max:255'],
            'trust_reviews_label' => ['required', 'string', 'max:255'],
            'trust_reviews_href' => ['required', 'string', 'max:255'],
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*.id' => ['nullable', 'integer', 'exists:hero_images,id'],
            'images.*.alt' => ['required', 'string', 'max:255'],
            'images.*.image' => ['required_without:images.*.id', 'nullable', 'image', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'eyebrow' => 'eyebrow',
            'title_before' => 'heading',
            'title_accent' => 'highlighted heading',
            'lede' => 'description',
            'bullets' => 'bullet points',
            'bullets.*' => 'bullet point',
            'cta_label' => 'button label',
            'cta_href' => 'button link',
            'trust_stars' => 'stars',
            'trust_text' => 'trust text',
            'trust_reviews_label' => 'reviews link label',
            'trust_reviews_href' => 'reviews link',
            'images' => 'images',
            'images.*.alt' => 'image description',
            'images.*.image' => 'image',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.*.image.required_without' => 'Choose a picture for every new image.',
        ];
    }
}
