<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCanvasBlock extends Model
{
    public const TYPE_INFO = 'info';

    public const TYPE_SKK = 'skk';

    public const TYPE_INTEGRATIONS = 'integrations';

    public const TYPE_FUNNEL = 'funnel';

    protected $fillable = [
        'project_id',
        'block_key',
        'block_type',
        'reference_id',
        'title',
        'pos_x',
        'pos_y',
        'width',
        'z_index',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
