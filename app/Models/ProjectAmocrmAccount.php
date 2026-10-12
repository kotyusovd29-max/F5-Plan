<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAmocrmAccount extends Model
{
    protected $fillable = [
        'project_id',
        'subdomain',
        'access_token',
        'account_id',
        'account_name',
        'last_exported_at',
        'last_export_result',
        'last_ai_audit_at',
        'last_ai_audit_result',
        'account_snapshot',
        'account_snapshot_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'last_exported_at' => 'datetime',
            'last_export_result' => 'array',
            'last_ai_audit_at' => 'datetime',
            'account_snapshot' => 'array',
            'account_snapshot_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function baseUrl(): string
    {
        return 'https://'.$this->subdomain.'.amocrm.ru';
    }
}
