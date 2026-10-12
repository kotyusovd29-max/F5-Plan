<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StarterPackageFunnel extends Model
{
    protected $fillable = [
        'starter_package_id',
        'name',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(StarterPackage::class, 'starter_package_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(StarterPackageFunnelStage::class)->orderBy('sort_order')->orderBy('id');
    }
}
