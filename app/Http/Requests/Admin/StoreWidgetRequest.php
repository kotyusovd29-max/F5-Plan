<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesWidgetAttributes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWidgetRequest extends FormRequest
{
    use ValidatesWidgetAttributes;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareWidgetBooleans();
    }

    public function rules(): array
    {
        return $this->widgetAttributeRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterPricingValidation($validator);
    }
}
