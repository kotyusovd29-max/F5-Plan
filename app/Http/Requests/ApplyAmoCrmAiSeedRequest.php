<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyAmoCrmAiSeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contacts' => ['nullable', 'array', 'max:50'],
            'contacts.*.name' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.custom_fields_values' => ['nullable', 'array'],
            'contacts.*.tags_to_add' => ['nullable', 'array'],
            'leads' => ['nullable', 'array', 'max:50'],
            'leads.*.name' => ['required_with:leads', 'string', 'max:255'],
            'leads.*.price' => ['nullable', 'numeric', 'min:0'],
            'leads.*.pipeline_id' => ['nullable', 'integer', 'min:1'],
            'leads.*.status_id' => ['nullable', 'integer', 'min:1'],
            'leads.*.custom_fields_values' => ['nullable', 'array'],
            'leads.*.tags_to_add' => ['nullable', 'array'],
            'leads.*._embedded' => ['nullable', 'array'],
            'links' => ['nullable', 'array', 'max:50'],
            'links.*.lead_id' => ['required_with:links', 'integer', 'min:1'],
            'links.*.contact_id' => ['required_with:links', 'integer', 'min:1'],
            'links.*.lead_name' => ['nullable', 'string', 'max:255'],
            'links.*.contact_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $contacts = $this->input('contacts', []);
            $leads = $this->input('leads', []);
            $links = $this->input('links', []);

            $emptyContacts = ! is_array($contacts) || $contacts === [];
            $emptyLeads = ! is_array($leads) || $leads === [];
            $emptyLinks = ! is_array($links) || $links === [];

            if ($emptyContacts && $emptyLeads && $emptyLinks) {
                $validator->errors()->add('contacts', 'Нет данных для создания. Сначала получите предпросмотр.');
            }
        });
    }
}
