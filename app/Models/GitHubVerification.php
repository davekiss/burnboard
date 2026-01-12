<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GitHubVerification extends Model
{
    protected $fillable = [
        'user_id',
        'github_commits',
        'github_prs_opened',
        'github_prs_merged',
        'github_lines_added',
        'github_lines_removed',
        'github_repos_active',
        'github_push_events',
        'verification_score',
        'is_verified',
        'verification_checks',
        'period',
        'period_start',
        'period_end',
        'fetched_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verification_checks' => 'array',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'fetched_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
