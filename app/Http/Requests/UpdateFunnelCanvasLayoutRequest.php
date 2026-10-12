<?php

namespace App\Http\Requests;

use App\Support\F5WidgetCatalog;
use App\Support\FunnelBoardWidgets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFunnelCanvasLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $slugs = F5WidgetCatalog::slugs();

        return [
            'stages' => ['sometimes', 'array'],
            'stages.*.id' => ['required', 'integer'],
            'stages.*.canvas_pos_x' => ['required', 'integer'],
            'stages.*.canvas_pos_y' => ['required', 'integer'],
            'viewport' => ['sometimes', 'array'],
            'viewport.offset_x' => ['required_with:viewport', 'integer'],
            'viewport.offset_y' => ['required_with:viewport', 'integer'],
            'viewport.zoom' => ['required_with:viewport', 'numeric', 'min:0.25', 'max:2'],
            'board_widgets' => ['sometimes', 'array', 'max:50'],
            'board_widgets.*.id' => ['nullable', 'uuid'],
            'board_widgets.*.widget_slug' => ['required_with:board_widgets', 'string', Rule::in($slugs)],
            'board_widgets.*.comment' => ['nullable', 'string', 'max:2000'],
            'board_widgets.*.canvas_pos_x' => ['required_with:board_widgets', 'integer', 'min:0'],
            'board_widgets.*.canvas_pos_y' => ['required_with:board_widgets', 'integer', 'min:0'],
            'board_widgets.*.next_type' => ['nullable', 'string', Rule::in(FunnelBoardWidgets::NEXT_TYPES)],
            'board_widgets.*.next_id' => ['nullable'],
            'board_widgets.*.path_points' => ['nullable', 'array', 'max:8'],
            'board_widgets.*.path_points.*.x' => ['required_with:board_widgets.*.path_points', 'integer', 'min:0'],
            'board_widgets.*.path_points.*.y' => ['required_with:board_widgets.*.path_points', 'integer', 'min:0'],
        ];
    }
}
