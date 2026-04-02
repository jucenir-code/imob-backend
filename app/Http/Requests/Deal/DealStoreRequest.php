<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'buyer_name' => ['nullable', 'string', 'max:120'],
            'buyer_contact' => ['nullable', 'string', 'max:50'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'commission_split' => ['nullable', 'array'],
            'commission_split.seller_agent' => ['required_with:commission_split', 'numeric', 'min:0', 'max:100'],
            'commission_split.buyer_agent' => ['required_with:commission_split', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('commission_split')) {
            $split = $this->input('commission_split');

            if (isset($split['seller_agent'])) {
                $split['seller_agent'] = (float) $split['seller_agent'];
            }

            if (isset($split['buyer_agent'])) {
                $split['buyer_agent'] = (float) $split['buyer_agent'];
            }

            $this->merge([
                'commission_split' => $split,
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $split = $this->input('commission_split');

            if (is_array($split)) {
                $total = ($split['seller_agent'] ?? 0) + ($split['buyer_agent'] ?? 0);
                if (abs($total - 100) > 0.01) {
                    $validator->errors()->add('commission_split', __('Commission split must sum 100%.'));
                }
            }
        });
    }
}
