<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends RegistrarModel
{
    protected $table = 'departments';

    protected $primaryKey = 'department_id';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class, 'department_id');
    }
}
