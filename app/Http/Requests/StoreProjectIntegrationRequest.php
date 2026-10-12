<?php

namespace App\Http\Requests;

use App\Models\ProjectIntegration;
use App\Support\F5WidgetCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'widget_slug' => ['nullable', 'string', Rule::in(F5WidgetCatalog::slugs())],
            'name' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'billing_quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'payment_type' => ['nullable', 'string', Rule::in(ProjectIntegration::PAYMENT_TYPES)],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $resolved = F5WidgetCatalog::resolveForStorage($this->all());

            if ($resolved['name'] === '') {
                $validator->errors()->add('name', 'Укажите виджет из каталога F5 или своё название.');
            }

            $isCustom = ($resolved['widget_slug'] ?? null) === null;
            $paymentType = $resolved['payment_type'] ?? null;

            if ($isCustom && $paymentType === ProjectIntegration::PAYMENT_ONE_TIME && $resolved['cost'] === null) {
                $validator->errors()->add('cost', 'Укажите сумму для единовременной оплаты.');
            }
        });
    }

    /**
     * @return array{
     *     widget_slug: ?string,
     *     widget_url: ?string,
     *     name: string,
     *     comment: ?string,
     *     billing_quantity: ?int,
     *     payment_type: ?string,
     *     cost: ?float
     * }
     */
    public function resolvedIntegrationData(): array
    {
        return F5WidgetCatalog::resolveForStorage($this->validated());
    }
}
