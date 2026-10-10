<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleRequestRecord extends RegistrarModel
{
    protected $table = 'schedule_request_records';

    protected $primaryKey = 'record_id';

    public const UPDATED_AT = null;

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }
}
