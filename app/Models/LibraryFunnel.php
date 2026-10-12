<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryFunnel extends Model
{
    protected $fillable = [
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

    public function stages(): HasMany
    {
        return $this->hasMany(LibraryFunnelStage::class)->orderBy('sort_order')->orderBy('id');
    }
}
