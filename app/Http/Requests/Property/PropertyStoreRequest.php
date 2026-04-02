<?php

namespace App\Http\Requests\Property;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_id' => ['sometimes', 'nullable', 'integer', 'exists:groups,id'],
            'type' => ['required', Rule::in(['apartment', 'house', 'land', 'commercial', 'development'])],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'parking' => ['nullable', 'integer', 'min:0'],
            'area_m2' => ['nullable', 'numeric', 'min:0'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'size:2'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_visibility' => ['nullable', Rule::in(['show', 'hide'])],
            'status' => ['nullable', Rule::in(['draft', 'active', 'reserved', 'sold'])],
        ];
    }
}
