<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleOpenRequest extends RegistrarModel
{
    protected $table = 'schedule_open_requests';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(ScheduleCopy::class, 'copy_id');
    }
}
