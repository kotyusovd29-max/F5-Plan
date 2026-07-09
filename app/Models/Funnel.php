<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Funnel extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'description',
        'sort_order',
        'canvas_offset_x',
        'canvas_offset_y',
        'canvas_zoom',
    ];

    protected function casts(): array
    {
        return [
            'canvas_zoom' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(FunnelStage::class)->orderBy('sort_order')->orderBy('id');
    }
}
