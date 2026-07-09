<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFunnelCanvasLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'stages' => ['sometimes', 'array'],
            'stages.*.id' => ['required', 'integer'],
            'stages.*.canvas_pos_x' => ['required', 'integer'],
            'stages.*.canvas_pos_y' => ['required', 'integer'],
            'viewport' => ['sometimes', 'array'],
            'viewport.offset_x' => ['required_with:viewport', 'integer'],
            'viewport.offset_y' => ['required_with:viewport', 'integer'],
            'viewport.zoom' => ['required_with:viewport', 'numeric', 'min:0.25', 'max:2'],
        ];
    }
}
