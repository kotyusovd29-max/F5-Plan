<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectIntegration extends Model
{
    public const PAYMENT_SUBSCRIPTION = 'subscription';

    public const PAYMENT_ONE_TIME = 'one_time';

    public const PAYMENT_TYPES = [
        self::PAYMENT_SUBSCRIPTION,
        self::PAYMENT_ONE_TIME,
    ];

    protected $fillable = [
        'project_id',
        'name',
        'widget_slug',
        'widget_url',
        'comment',
        'billing_quantity',
        'payment_type',
        'cost',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'billing_quantity' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isCustom(): bool
    {
        return $this->widget_slug === null || $this->widget_slug === '';
    }

    public function isOneTimePayment(): bool
    {
        return $this->isCustom() && $this->payment_type === self::PAYMENT_ONE_TIME;
    }
}
