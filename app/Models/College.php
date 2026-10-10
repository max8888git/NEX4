<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class College extends RegistrarModel
{
    protected $table = 'colleges';

    protected $primaryKey = 'college_id';

    protected $casts = [
        'use_departments' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'college_id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class, 'college_id');
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class, 'exclusive_college_id');
    }
}
