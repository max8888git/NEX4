<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends RegistrarModel
{
    protected $table = 'floors';

    protected $primaryKey = 'floor_id';

    public $timestamps = false;

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'floor_id');
    }

    public function colleges(): BelongsToMany
    {
        return $this->belongsToMany(College::class, 'floor_college_access', 'floor_id', 'college_id');
    }
}
