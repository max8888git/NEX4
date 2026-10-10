<?php

namespace App\Models;

class TimeSlot extends RegistrarModel
{
    protected $table = 'time_slots';

    protected $primaryKey = 'slot_index';

    public $incrementing = false;

    public $timestamps = false;
}
