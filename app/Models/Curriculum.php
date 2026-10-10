<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculum extends RegistrarModel
{
    protected $table = 'curricula';

    protected $primaryKey = 'curriculum_id';

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(CurriculumCourse::class, 'curriculum_id');
    }
}
