<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibrarySkkField extends Model
{
    protected $fillable = [
        'entity_type',
        'name',
        'value_type',
        'is_required',
        'show_in_card',
        'show_in_list',
        'show_in_filter',
        'options',
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

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function normalizeAttributes(array $attributes): array
    {
        $normalized = ProjectSkkField::normalizeAttributes($attributes);

        unset($normalized['funnel_stage_id']);

        $normalized['entity_type'] = in_array($attributes['entity_type'] ?? null, ProjectSkkField::TYPES, true)
            ? $attributes['entity_type']
            : ProjectSkkField::TYPE_DEALS;

        $stageName = isset($attributes['stage_name']) ? trim((string) $attributes['stage_name']) : '';
        $normalized['stage_name'] = $stageName !== '' ? $stageName : null;

        if (array_key_exists('sort_order', $attributes)) {
            $normalized['sort_order'] = (int) $attributes['sort_order'];
        }

        return $normalized;
    }
}
