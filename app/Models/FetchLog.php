<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FetchLog extends Model
{
    protected $fillable = [
        'source', 'mode', 'dry_run', 'publish', 'since',
        'created_count', 'skipped_count', 'failed_count',
        'errors', 'status', 'started_by', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'publish' => 'boolean',
            'since' => 'date',
            'errors' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
