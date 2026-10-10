<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends RegistrarModel
{
    protected $table = 'programs';

    protected $primaryKey = 'program_id';

    protected $casts = [
        'represents_college' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(Curriculum::class, 'program_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'program_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_programs', 'program_id', 'user_id');
    }
}
