<?php

namespace App\Http\Requests\Auth;

use App\Support\CorporateEmail;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => CorporateEmail::normalize($this->input('email'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', CorporateEmail::rule()],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.regex' => 'Access is restricted to @'.CorporateEmail::domain().' email accounts.',
        ];
    }
}
