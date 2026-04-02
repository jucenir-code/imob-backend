<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buyer_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'buyer_contact' => ['sometimes', 'nullable', 'string', 'max:50'],
            'commission_percent' => ['sometimes', 'numeric', 'min:0', 'max:20'],
            'commission_split' => ['sometimes', 'array'],
            'commission_split.seller_agent' => ['required_with:commission_split', 'numeric', 'min:0', 'max:100'],
            'commission_split.buyer_agent' => ['required_with:commission_split', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', Rule::in(['initiated', 'under_review', 'proposal', 'in_escrow', 'closed', 'lost', 'cancelled'])],
            'notes' => ['sometimes', 'nullable', 'string'],
            'closed_at' => ['sometimes', 'nullable', 'date'],
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
