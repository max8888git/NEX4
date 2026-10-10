<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumImport extends RegistrarModel
{
    protected $table = 'curriculum_imports';

    protected $primaryKey = 'import_id';

    public $timestamps = false;

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(CurriculumImportRow::class, 'import_id');
    }
}
