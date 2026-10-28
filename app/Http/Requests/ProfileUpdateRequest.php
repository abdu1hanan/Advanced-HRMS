<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\PhoneNumber;
use App\Rules\StrictEmail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isGuest = $this->user()?->isGuest() ?? false;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
                new StrictEmail,
            ],
            'salary_slip_email' => ['nullable', 'string', 'lowercase', 'max:255', new StrictEmail],
            'phone'             => ['required', new PhoneNumber],
            'emergency_number'  => [$isGuest ? 'nullable' : 'required', new PhoneNumber],
            'address'           => ['nullable', 'string', 'max:500'],
            'department_id'     => ['nullable', 'exists:departments,id'],
        ];
    }
}
