<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends RegistrarModel
{
    protected $table = 'sections';

    protected $primaryKey = 'section_id';

    protected $casts = [
        'is_retake' => 'boolean',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(ClassOffering::class, 'section_id');
    }
}
