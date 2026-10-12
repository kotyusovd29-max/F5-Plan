<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryFunnelStage extends Model
{
    protected $fillable = [
        'library_funnel_id',
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
    ];

    protected function casts(): array
    {
        return [
            'outcomes' => 'array',
            'widgets' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function libraryFunnel(): BelongsTo
    {
        return $this->belongsTo(LibraryFunnel::class);
    }
}
