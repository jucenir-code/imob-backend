<?php

namespace App\Http\Requests\Property;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'string'],
            'bedrooms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'bathrooms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'parking' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'area_m2' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'neighborhood' => ['sometimes', 'string', 'max:120'],
            'city' => ['sometimes', 'string', 'max:80'],
            'state' => ['sometimes', 'string', 'size:2'],
            'lat' => ['sometimes', 'nullable', 'numeric'],
            'lng' => ['sometimes', 'nullable', 'numeric'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price_visibility' => ['sometimes', Rule::in(['show', 'hide'])],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'reserved', 'sold'])],
        ];
    }
}
