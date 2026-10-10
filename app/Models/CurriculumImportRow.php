<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumImportRow extends RegistrarModel
{
    protected $table = 'curriculum_import_rows';

    protected $primaryKey = 'import_row_id';

    public $timestamps = false;

    public function import(): BelongsTo
    {
        return $this->belongsTo(CurriculumImport::class, 'import_id');
    }
}
