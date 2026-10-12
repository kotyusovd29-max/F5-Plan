<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectWorkStage extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'composition',
        'cost',
        'duration_days',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'duration_days' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
