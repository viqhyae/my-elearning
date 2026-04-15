<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'slug',
    'description',
    'level',
    'duration_weeks',
    'category',
    'mentor_user_id',
    'trailer_video_url',
    'tools',
    'is_published',
    'published_at',
])]
class Course extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class, 'course_id')->orderBy('order_no');
    }
}
