<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\CorporateEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => CorporateEmail::normalize($this->input('email')),
            'phone' => preg_replace('/[\s-]/', '', (string) $this->input('phone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'role' => ['required', Rule::in(config('costflow.roles'))],

            // Malaysian mobile (01x-xxxxxxx) or landline (03-xxxxxxx), with
            // optional +60 prefix and free-form spaces / dashes.
            'phone' => ['required', 'string', 'regex:/^(\+?60|0)(1\d{8,9}|[3-9]\d{7,8})$/'],

            'email' => ['required', 'string', CorporateEmail::rule(), Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.regex' => 'Email must be a valid @'.CorporateEmail::domain().' address.',
            'email.unique' => 'An account with that email already exists — try signing in.',
            'phone.regex' => 'Please enter a valid Malaysian phone number, e.g. 012-3456789.',
            'role.in' => 'Please select your role in BPE (Engineer, Management or IT).',
        ];
    }
}
