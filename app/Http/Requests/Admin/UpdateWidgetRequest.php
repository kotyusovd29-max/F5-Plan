<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesWidgetAttributes;
use App\Models\F5Widget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWidgetRequest extends FormRequest
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
        /** @var F5Widget $widget */
        $widget = $this->route('widget');

        return $this->widgetAttributeRules($widget->id);
    }

    public function withValidator(Validator $validator): void
    {
        $this->afterPricingValidation($validator);
    }
}
