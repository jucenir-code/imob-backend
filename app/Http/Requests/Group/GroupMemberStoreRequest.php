<?php

namespace App\Http\Requests\Group;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class GroupMemberStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required_without:email', 'integer', 'exists:users,id'],
            'email' => ['required_without:user_id', 'email', 'exists:users,email'],
            'role_in_group' => ['sometimes', 'string', 'in:moderator,member'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email') && ! $this->filled('user_id')) {
            $userId = User::where('email', $this->string('email')->trim())->value('id');

            if ($userId) {
                $this->merge(['user_id' => $userId]);
            }
        }
    }
}
