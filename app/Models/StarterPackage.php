<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StarterPackage extends Model
{
    protected $fillable = [
        'name',
        'business_sphere',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function funnels(): HasMany
    {
        return $this->hasMany(StarterPackageFunnel::class)->orderBy('sort_order')->orderBy('id');
    }

    public function skkFields(): HasMany
    {
        return $this->hasMany(StarterPackageSkkField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(StarterPackageIntegration::class)->orderBy('sort_order')->orderBy('id');
    }

    public function workStages(): HasMany
    {
        return $this->hasMany(StarterPackageWorkStage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
