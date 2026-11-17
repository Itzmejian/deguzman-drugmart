<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Wala nang Classification sa form: Product Type na lang (Branded / Generic / Grocery).
     * Ang `category` ay awtomatiko: Grocery kapag grocery; kung hindi, naiiwan ang dati (otc kapag bago).
     */
    protected function prepareForValidation(): void
    {
        $type = $this->input('type', 'generic');
        $current = $this->route('product')?->category;

        // Rx checkbox; kapag wala sa request, naiiwan ang dati
        $rx = $this->has('requires_rx') ? $this->boolean('requires_rx') : $current === 'rx';

        $category = $type === 'grocery' ? 'grocery' : ($rx ? 'rx' : 'otc');

        $this->merge(['type' => $type, 'category' => $category]);

        // Grocery lang ang puwedeng walang expiration date (hal. napkin, sabon)
        if ($this->noExpiry()) {
            $this->merge(['expiration_date' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'price'           => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'stock'           => ['required', 'integer', 'min:0', 'max:1000000'],
            'expiration_date' => $this->noExpiry()
                ? ['nullable']
                : ($this->isMethod('POST') ? ['required', 'date', 'after:today'] : ['required', 'date']),
            'category'        => ['required', 'in:otc,rx,grocery'],
            'type'            => ['required', 'in:branded,generic,grocery'],
            'unit'            => ['required', 'string', 'max:30'],
            'subtitle'        => ['nullable', 'string', 'max:120'],
            'photo'           => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp,gif,bmp,avif', 'max:10240'],
            'description'     => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function noExpiry(): bool
    {
        return $this->input('type') === 'grocery' && $this->boolean('no_expiry');
    }

    public function messages(): array
    {
        return [
            'expiration_date.required' => 'Enter the expiration date. For Grocery items that never expire, tick "No expiration date".',
            'expiration_date.after' => 'The expiration date must be a date after today. Expired items cannot be added.',
            'price.min'      => 'The price must be at least ₱0.01.',
            'photo.uploaded' => 'The photo failed to upload. Please try a smaller PNG or JPG image.',
            'photo.image'    => 'The photo must be an image (PNG, JPG, JPEG, WEBP, GIF or BMP).',
            'photo.mimes'    => 'The photo must be a PNG, JPG, JPEG, WEBP, GIF or BMP file.',
            'photo.max'      => 'The photo may not be larger than 10MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'product name',
            'stock' => 'stock quantity',
            'expiration_date' => 'expiration date',
            'type' => 'product type',
            'category' => 'classification',
        ];
    }
}
