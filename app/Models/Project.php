<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'employees_count',
        'client_tasks',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class)->orderBy('sort_order')->orderBy('id');
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(ProjectIntegration::class)->orderBy('sort_order')->orderBy('id');
    }

    public function skkFields(): HasMany
    {
        return $this->hasMany(ProjectSkkField::class)->orderBy('entity_type')->orderBy('sort_order')->orderBy('id');
    }

    public function canvasBlocks(): HasMany
    {
        return $this->hasMany(ProjectCanvasBlock::class)->orderBy('z_index')->orderBy('id');
    }

    public function aiMessages(): HasMany
    {
        return $this->hasMany(ProjectAiMessage::class)->orderBy('id');
    }
}
