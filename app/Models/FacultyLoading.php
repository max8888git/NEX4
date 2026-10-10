<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyLoading extends RegistrarModel
{
    protected $table = 'faculty_loadings';

    protected $primaryKey = 'loading_id';

    public const CREATED_AT = null;

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
