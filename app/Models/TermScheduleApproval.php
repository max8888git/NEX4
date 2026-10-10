<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermScheduleApproval extends RegistrarModel
{
    protected $table = 'term_schedule_approvals';

    protected $primaryKey = 'term_id';

    public $incrementing = false;

    public $timestamps = false;

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }
}
