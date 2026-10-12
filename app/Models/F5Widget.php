<?php

namespace App\Models;

use App\Support\F5WidgetCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class F5Widget extends Model
{
    protected $table = 'f5_widgets';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'price',
        'category',
        'url',
        'icon',
        'pricing',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pricing' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => F5WidgetCatalog::forgetCache());
        static::deleted(fn () => F5WidgetCatalog::forgetCache());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toCatalogArray(): array
    {
        $payload = [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => (string) ($this->description ?? ''),
            'price' => (string) ($this->price ?? ''),
            'category' => $this->category,
            'url' => $this->url,
            'icon' => $this->icon,
        ];

        if (is_array($this->pricing) && $this->pricing !== []) {
            $payload['pricing'] = $this->pricing;
        }

        return $payload;
    }
}
