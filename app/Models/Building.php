<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends RegistrarModel
{
    protected $table = 'buildings';

    protected $primaryKey = 'building_id';

    protected $casts = [
        'uses_floors' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'exclusive_college_id');
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class, 'building_id');
    }
}
