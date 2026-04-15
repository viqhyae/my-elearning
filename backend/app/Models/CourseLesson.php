<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'module_id',
    'title',
    'description',
    'video_url',
    'topics',
    'tools',
    'duration_minutes',
    'order_no',
    'is_published',
])]
class CourseLesson extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'tools' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'module_id');
    }
}
