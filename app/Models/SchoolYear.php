<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolYear extends RegistrarModel
{
    protected $table = 'school_years';

    protected $primaryKey = 'school_year_id';

    public $timestamps = false;

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class, 'school_year_id');
    }
}
