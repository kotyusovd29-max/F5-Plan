<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSkkField extends Model
{
    public const TYPE_DEALS = 'deals';

    public const TYPE_CONTACTS = 'contacts';

    public const TYPE_COMPANIES = 'companies';

    public const TYPES = [
        self::TYPE_DEALS,
        self::TYPE_CONTACTS,
        self::TYPE_COMPANIES,
    ];

    public const TYPE_LABELS = [
        self::TYPE_DEALS => 'Сделки',
        self::TYPE_CONTACTS => 'Контакты',
        self::TYPE_COMPANIES => 'Компании',
    ];

    public const VALUE_TEXT = 'text';

    public const VALUE_NUMBER = 'number';

    public const VALUE_DATE = 'date';

    public const VALUE_SELECT = 'select';

    public const VALUE_MULTISELECT = 'multiselect';

    public const VALUE_TOGGLE = 'toggle';

    public const VALUE_TYPES = [
        self::VALUE_TEXT,
        self::VALUE_NUMBER,
        self::VALUE_DATE,
        self::VALUE_SELECT,
        self::VALUE_MULTISELECT,
        self::VALUE_TOGGLE,
    ];

    public const VALUE_TYPE_LABELS = [
        self::VALUE_TEXT => 'Текст',
        self::VALUE_NUMBER => 'Число',
        self::VALUE_DATE => 'Дата',
        self::VALUE_SELECT => 'Список',
        self::VALUE_MULTISELECT => 'Мультисписок',
        self::VALUE_TOGGLE => 'Переключатель',
    ];

    public const SECTION_LABEL = 'Поля';

    protected $fillable = [
        'project_id',
        'entity_type',
        'name',
        'value_type',
        'is_required',
        'show_in_card',
        'show_in_list',
        'show_in_filter',
        'options',
        'funnel_stage_id',
        'comment',
        'sort_order',
        'amocrm_id',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'show_in_card' => 'boolean',
            'show_in_list' => 'boolean',
            'show_in_filter' => 'boolean',
            'options' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function funnelStage(): BelongsTo
    {
        return $this->belongsTo(FunnelStage::class);
    }

    public function needsOptions(): bool
    {
        return in_array($this->value_type, [self::VALUE_SELECT, self::VALUE_MULTISELECT], true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function normalizeAttributes(array $attributes): array
    {
        $valueType = $attributes['value_type'] ?? self::VALUE_TEXT;

        if (! in_array($valueType, self::VALUE_TYPES, true)) {
            $valueType = self::VALUE_TEXT;
        }

        $normalized = [
            'name' => trim((string) ($attributes['name'] ?? '')),
            'value_type' => $valueType,
            'is_required' => (bool) ($attributes['is_required'] ?? false),
            'show_in_card' => array_key_exists('show_in_card', $attributes)
                ? (bool) $attributes['show_in_card']
                : true,
            'show_in_list' => (bool) ($attributes['show_in_list'] ?? false),
            'show_in_filter' => (bool) ($attributes['show_in_filter'] ?? false),
            'funnel_stage_id' => ! empty($attributes['funnel_stage_id'])
                ? (int) $attributes['funnel_stage_id']
                : null,
            'comment' => isset($attributes['comment']) && trim((string) $attributes['comment']) !== ''
                ? trim((string) $attributes['comment'])
                : null,
        ];

        $normalized['options'] = self::normalizeOptions($attributes['options'] ?? null, $valueType);

        return $normalized;
    }

    /**
     * @return list<string>|null
     */
    public static function normalizeOptions(mixed $options, ?string $valueType = null): ?array
    {
        if ($valueType !== null && ! in_array($valueType, [self::VALUE_SELECT, self::VALUE_MULTISELECT], true)) {
            return null;
        }

        if ($options === null || $options === '') {
            return null;
        }

        if (is_string($options)) {
            $lines = preg_split('/\r\n|\r|\n/', $options) ?: [];
            $options = $lines;
        }

        if (! is_array($options)) {
            return null;
        }

        $normalized = [];

        foreach ($options as $option) {
            $option = trim((string) $option);

            if ($option !== '') {
                $normalized[] = $option;
            }
        }

        return $normalized === [] ? null : array_values($normalized);
    }
}
