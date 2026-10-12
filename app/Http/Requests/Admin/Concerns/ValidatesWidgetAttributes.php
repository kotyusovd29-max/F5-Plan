<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Support\F5WidgetCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesWidgetAttributes
{
    /**
     * @return array<string, mixed>
     */
    protected function widgetAttributeRules(?int $ignoreId = null): array
    {
        $slugRule = Rule::unique('f5_widgets', 'slug');

        if ($ignoreId !== null) {
            $slugRule = $slugRule->ignore($ignoreId);
        }

        return [
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', $slugRule, Rule::notIn([F5WidgetCatalog::CUSTOM_SLUG])],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(F5WidgetCatalog::categories()))],
            'url' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'pricing_model' => ['nullable', 'string', Rule::in(['', 'per_channel_monthly', 'fixed_period'])],
            'pricing_unit_monthly' => ['nullable', 'numeric', 'min:0'],
            'pricing_quantity_label' => ['nullable', 'string', 'max:100'],
            'pricing_default_quantity' => ['nullable', 'integer', 'min:1'],
            'pricing_min_quantity' => ['nullable', 'integer', 'min:1'],
            'pricing_package_6' => ['nullable', 'numeric', 'min:0'],
            'pricing_package_12' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareWidgetBooleans(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function widgetPayload(): array
    {
        $data = $this->safe()->only([
            'slug',
            'name',
            'description',
            'price',
            'category',
            'url',
            'is_active',
            'sort_order',
        ]);

        return [
            'slug' => (string) $data['slug'],
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'] ?? null,
            'category' => (string) $data['category'],
            'url' => filled($data['url'] ?? null) ? (string) $data['url'] : null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'pricing' => $this->resolvedPricing(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolvedPricing(): ?array
    {
        $model = (string) ($this->input('pricing_model') ?? '');

        if ($model === 'per_channel_monthly') {
            $min = max(1, (int) ($this->input('pricing_min_quantity') ?? 1));
            $default = max($min, (int) ($this->input('pricing_default_quantity') ?? $min));

            return [
                'model' => 'per_channel_monthly',
                'unit_monthly' => (float) ($this->input('pricing_unit_monthly') ?? 0),
                'quantity_label' => (string) ($this->input('pricing_quantity_label') ?: 'каналов'),
                'default_quantity' => $default,
                'min_quantity' => $min,
            ];
        }

        if ($model === 'fixed_period') {
            $packages = array_filter([
                6 => $this->input('pricing_package_6'),
                12 => $this->input('pricing_package_12'),
            ], fn ($value) => $value !== null && $value !== '');

            if ($packages === []) {
                return null;
            }

            return [
                'model' => 'fixed_period',
                'packages' => array_map(fn ($value) => (float) $value, $packages),
            ];
        }

        return null;
    }

    protected function afterPricingValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $model = (string) ($this->input('pricing_model') ?? '');

            if ($model === 'per_channel_monthly' && ! filled($this->input('pricing_unit_monthly'))) {
                $validator->errors()->add('pricing_unit_monthly', 'Укажите цену за канал.');
            }

            if ($model === 'fixed_period'
                && ! filled($this->input('pricing_package_6'))
                && ! filled($this->input('pricing_package_12'))
            ) {
                $validator->errors()->add('pricing_package_6', 'Укажите хотя бы один пакет.');
            }
        });
    }
}
