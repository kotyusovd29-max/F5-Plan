<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StarterPackageWorkStage extends Model
{
    protected $fillable = [
        'starter_package_id',
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
            'sort_order' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(StarterPackage::class, 'starter_package_id');
    }
}
