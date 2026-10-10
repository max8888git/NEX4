<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionPlan extends RegistrarModel
{
    protected $table = 'section_plans';

    protected $primaryKey = 'section_plan_id';

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }
}
