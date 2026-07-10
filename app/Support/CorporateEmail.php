<?php

namespace App\Support;

class CorporateEmail
{
    /**
     * The sign-in form lets people type just "alfi" — the corporate suffix is
     * appended for them. Mirrors fullEmail() in the original prototype.
     */
    public static function normalize(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return '';
        }

        return str_contains($value, '@')
            ? $value
            : $value.'@'.config('costflow.email_domain');
    }

    public static function domain(): string
    {
        return (string) config('costflow.email_domain');
    }

    /**
     * Validation rule matching a well-formed address on the corporate domain.
     */
    public static function rule(): string
    {
        return 'regex:/^[a-z0-9._%+\-]+@'.preg_quote(self::domain(), '/').'$/';
    }
}
