<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionSubjectOverride extends RegistrarModel
{
    protected $table = 'section_subject_overrides';

    protected $primaryKey = 'override_id';

    public const UPDATED_AT = null;

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(CurriculumCourse::class, 'curriculum_course_id');
    }
}
