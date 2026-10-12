<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectAiMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const THREAD_PROJECT = 'project';

    public const THREAD_MEETING = 'meeting';

    public const THREAD_AUDIT_RECOMMENDATIONS = 'audit_recommendations';

    public const THREAD_KP_GENERATION = 'kp_generation';

    /**
     * @return list<string>
     */
    public static function threads(): array
    {
        return [
            self::THREAD_PROJECT,
            self::THREAD_MEETING,
            self::THREAD_AUDIT_RECOMMENDATIONS,
            self::THREAD_KP_GENERATION,
        ];
    }

    public function isExportableThread(): bool
    {
        return in_array($this->thread, [
            self::THREAD_MEETING,
            self::THREAD_AUDIT_RECOMMENDATIONS,
        ], true);
    }

    public function threadLabel(): string
    {
        return match ($this->thread) {
            self::THREAD_MEETING => 'встреча',
            self::THREAD_AUDIT_RECOMMENDATIONS => 'рекомендации по аудиту',
            self::THREAD_KP_GENERATION => 'генерация КП',
            default => 'план',
        };
    }

    public function threadLabelShort(): string
    {
        return match ($this->thread) {
            self::THREAD_MEETING => 'Встреча',
            self::THREAD_AUDIT_RECOMMENDATIONS => 'Аудит',
            self::THREAD_KP_GENERATION => 'КП',
            default => 'План',
        };
    }

    protected $attributes = [
        'status' => self::STATUS_COMPLETED,
        'thread' => self::THREAD_PROJECT,
    ];

    protected $fillable = [
        'project_id',
        'user_id',
        'thread',
        'brief_document_id',
        'brief_document_ids',
        'role',
        'status',
        'content',
        'error_message',
        'changes',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'brief_document_ids' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function briefDocument(): BelongsTo
    {
        return $this->belongsTo(ProjectBriefDocument::class, 'brief_document_id');
    }

    /**
     * Все прикреплённые к сообщению документы (мультивложения).
     *
     * @return \Illuminate\Support\Collection<int, ProjectBriefDocument>
     */
    public function briefDocuments(): \Illuminate\Support\Collection
    {
        $ids = $this->brief_document_ids;

        if (! is_array($ids) || $ids === []) {
            $ids = $this->brief_document_id ? [$this->brief_document_id] : [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return collect();
        }

        $documents = ProjectBriefDocument::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $documents->get($id))
            ->filter()
            ->values();
    }

    public function applySnapshot(): HasOne
    {
        return $this->hasOne(ProjectAiApplySnapshot::class, 'project_ai_message_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function proposalChanges(): ?array
    {
        $changes = $this->getAttribute('changes');

        return is_array($changes) ? $changes : null;
    }

    public function hasPendingChanges(): bool
    {
        return $this->role === self::ROLE_ASSISTANT
            && $this->status === self::STATUS_COMPLETED
            && $this->applied_at === null
            && $this->changesHasOperations();
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function changesHasOperations(): bool
    {
        $changes = $this->proposalChanges();

        if ($changes === null) {
            return false;
        }

        foreach ($changes as $section => $operations) {
            if ($section === 'project') {
                if (! empty($operations['fields'])) {
                    return true;
                }

                continue;
            }

            if (! is_array($operations)) {
                continue;
            }

            foreach (['create', 'update', 'delete'] as $action) {
                if (! empty($operations[$action])) {
                    return true;
                }
            }
        }

        return false;
    }
}
