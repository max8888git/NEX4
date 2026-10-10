<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMeeting extends RegistrarModel
{
    protected $table = 'class_meetings';

    protected $primaryKey = 'meeting_id';

    public function offering(): BelongsTo
    {
        return $this->belongsTo(ClassOffering::class, 'offering_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
