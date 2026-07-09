<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCanvasLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'blocks' => ['sometimes', 'array'],
            'blocks.*.id' => ['required', 'integer'],
            'blocks.*.pos_x' => ['required', 'integer'],
            'blocks.*.pos_y' => ['required', 'integer'],
            'blocks.*.z_index' => ['sometimes', 'integer', 'min:1'],
            'viewport' => ['sometimes', 'array'],
            'viewport.offset_x' => ['required_with:viewport', 'integer'],
            'viewport.offset_y' => ['required_with:viewport', 'integer'],
            'viewport.zoom' => ['required_with:viewport', 'numeric', 'min:0.25', 'max:2'],
        ];
    }
}
