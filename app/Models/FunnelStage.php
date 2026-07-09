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
        'color',
        'sort_order',
        'canvas_pos_x',
        'canvas_pos_y',
    ];

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function barColor(): string
    {
        return FunnelStageColors::resolve($this->color, (int) $this->sort_order);
    }
}
