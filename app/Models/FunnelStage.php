<?php

namespace App\Models;

use App\Support\FunnelStageColors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelStage extends Model
{
    protected $fillable = [
        'funnel_id',
        'name',
        'action',
        'comment',
        'stage_type',
        'deadline',
        'kind',
        'outcomes',
        'widgets',
        'color',
        'sort_order',
        'amocrm_id',
        'canvas_pos_x',
        'canvas_pos_y',
    ];

    protected function casts(): array
    {
        return [
            'outcomes' => 'array',
            'widgets' => 'array',
            'sort_order' => 'integer',
            'canvas_pos_x' => 'integer',
            'canvas_pos_y' => 'integer',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function barColor(): string
    {
        return FunnelStageColors::resolve($this->color, (int) $this->sort_order);
    }
}
