<?php

namespace App\Http\Requests;

use App\Models\WccRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWccRequest extends FormRequest
{
    /**
     * Authorize before validating, so a caller who may not touch this record
     * gets a 403 rather than a 422 itemising the fields they got wrong.
     */
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record
            ? $this->user()->can('update', $record)
            : $this->user()->can('create', WccRecord::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quo_no' => ['required', 'string', 'max:100'],
            'client' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'dept' => ['nullable', 'string', 'max:100'],
            'manager' => ['nullable', 'string', 'max:100'],

            'planned_cost' => ['required', 'numeric', 'min:0'],
            'selling' => ['required', 'numeric', 'min:0'],
            'actual' => ['required', 'numeric', 'min:0'],

            // Verbatim output of the template engine's cap(). Stored, never parsed.
            // Images live in wcc_attachments, so this stays small.
            'snapshot' => ['nullable', 'string', 'json', 'max:2000000'],

            // The version the client last saw. Required when overwriting an
            // existing record, so a stale tab cannot clobber a newer save.
            'version' => [Rule::requiredIf(fn () => $this->route('record') !== null), 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'manager' => strtoupper(trim((string) $this->input('manager'))) ?: null,
        ]);
    }
}
