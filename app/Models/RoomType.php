<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends RegistrarModel
{
    protected $table = 'room_types';

    protected $primaryKey = 'room_type_id';

    public const UPDATED_AT = null;

    protected $casts = [
        'requires_campus_room' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'room_type_id');
    }
}
