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

    public const SECTION_LABEL = 'Поля';

    protected $fillable = [
        'project_id',
        'entity_type',
        'name',
        'comment',
        'sort_order',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
