<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Support\WidgetPricingCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectAmocrmLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $project->canBeEditedBy($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amocrm_license_tariff' => [
                'nullable',
                'string',
                Rule::in(WidgetPricingCalculator::amocrmLicenseTariffSlugs()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amocrm_license_tariff.in' => 'Выберите один из тарифов: Базовый, Расширенный или Профессиональный.',
        ];
    }
}
