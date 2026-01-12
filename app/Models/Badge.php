<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'category',
        'tier',
        'icon',
        'is_hidden',
        'is_active',
        'requirements',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tier' => 'integer',
            'is_hidden' => 'boolean',
            'is_active' => 'boolean',
            'requirements' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot(['earned_at', 'metadata'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // Category constants
    public const CATEGORY_EFFICIENCY = 'efficiency';

    public const CATEGORY_STREAK = 'streak';

    public const CATEGORY_MILESTONE = 'milestone';

    public const CATEGORY_COMPETITIVE = 'competitive';

    public const CATEGORY_TIME = 'time';

    public const CATEGORY_HIDDEN = 'hidden';
}
