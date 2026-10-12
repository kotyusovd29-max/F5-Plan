<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ProjectBriefDocument extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'kind',
        'original_name',
        'disk_path',
        'mime_type',
        'size',
        'extracted_text',
        'char_count',
    ];

    public const KIND_TZ = 'tz';

    public const KIND_KP = 'kp';

    public const KIND_CHAT = 'chat';

    public const KINDS = [
        self::KIND_TZ,
        self::KIND_KP,
        self::KIND_CHAT,
    ];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiMessages(): HasMany
    {
        return $this->hasMany(ProjectAiMessage::class, 'brief_document_id');
    }

    public function isImage(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION));

        return in_array($extension, self::IMAGE_EXTENSIONS, true);
    }

    public function toDataUrl(): ?string
    {
        if (! $this->isImage() || ! $this->disk_path) {
            return null;
        }

        if (! Storage::disk('local')->exists($this->disk_path)) {
            return null;
        }

        $binary = Storage::disk('local')->get($this->disk_path);
        if ($binary === null || $binary === '') {
            return null;
        }

        $mime = strtolower((string) $this->mime_type);
        if ($mime === '' || ! str_starts_with($mime, 'image/')) {
            $extension = strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => 'image/jpeg',
            };
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }
}
