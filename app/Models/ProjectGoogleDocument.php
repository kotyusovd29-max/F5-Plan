<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectGoogleDocument extends Model
{
    public const SOURCE_PROJECT_PLAN = 'project_plan';

    public const SOURCE_MEETING = 'meeting';

    public const SOURCE_ESTIMATE = 'estimate';

    public const SOURCE_AMOCRM_AI_AUDIT = 'amocrm_ai_audit';

    public const SOURCE_AUDIT_RECOMMENDATIONS = 'audit_recommendations';

    public const SOURCE_KP_PRESENTATION = 'kp_presentation';

    protected $fillable = [
        'project_id',
        'user_id',
        'google_file_id',
        'name',
        'url',
        'source',
        'project_ai_message_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiMessage(): BelongsTo
    {
        return $this->belongsTo(ProjectAiMessage::class, 'project_ai_message_id');
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_MEETING => 'Первая встреча',
            self::SOURCE_ESTIMATE => 'Состав работ и смета',
            self::SOURCE_AMOCRM_AI_AUDIT => 'AI-аудит amoCRM',
            self::SOURCE_AUDIT_RECOMMENDATIONS => 'Рекомендации по аудиту',
            self::SOURCE_KP_PRESENTATION => 'КП (презентация)',
            default => 'План проекта',
        };
    }
}
