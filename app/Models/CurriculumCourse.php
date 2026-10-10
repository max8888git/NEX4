<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumCourse extends RegistrarModel
{
    protected $table = 'curriculum_courses';

    protected $primaryKey = 'curriculum_course_id';

    protected $casts = [
        'is_elective' => 'boolean',
        'lecture_units' => 'decimal:2',
        'lab_units' => 'decimal:2',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }

    public function roomRequirements(): HasMany
    {
        return $this->hasMany(CourseRoomRequirement::class, 'curriculum_course_id');
    }
}
