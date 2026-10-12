<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelBoardComment extends Model
{
    protected $fillable = [
        'funnel_id',
        'funnel_stage_id',
        'author_name',
        'body',
        'ip_hash',
    ];

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(FunnelStage::class, 'funnel_stage_id');
    }

    /**
     * @return array{id: int, funnel_stage_id: int|null, author_name: string, body: string, created_at: string|null}
     */
    public function toFrontend(): array
    {
        return [
            'id' => $this->id,
            'funnel_stage_id' => $this->funnel_stage_id !== null ? (int) $this->funnel_stage_id : null,
            'author_name' => (string) $this->author_name,
            'body' => (string) $this->body,
            'created_at' => $this->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
        ];
    }
}
