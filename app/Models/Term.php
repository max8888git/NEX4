<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends RegistrarModel
{
    protected $table = 'terms';

    protected $primaryKey = 'term_id';

    public $timestamps = false;

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'term_id');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(ClassOffering::class, 'term_id');
    }
}
