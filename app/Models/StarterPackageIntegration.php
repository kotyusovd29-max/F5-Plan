<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StarterPackageIntegration extends Model
{
    protected $fillable = [
        'starter_package_id',
        'name',
        'widget_slug',
        'widget_url',
        'comment',
        'billing_quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'billing_quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(StarterPackage::class, 'starter_package_id');
    }
}
