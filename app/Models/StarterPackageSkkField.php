<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StarterPackageSkkField extends Model
{
    protected $fillable = [
        'starter_package_id',
        'entity_type',
        'name',
        'value_type',
        'is_required',
        'show_in_card',
        'show_in_list',
        'show_in_filter',
        'options',
        'funnel_name',
        'stage_name',
        'comment',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'show_in_card' => 'boolean',
            'show_in_list' => 'boolean',
            'show_in_filter' => 'boolean',
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(StarterPackage::class, 'starter_package_id');
    }
}
