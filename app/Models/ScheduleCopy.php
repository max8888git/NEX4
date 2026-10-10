<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleCopy extends RegistrarModel
{
    protected $table = 'schedule_copies';

    protected $primaryKey = 'copy_id';

    protected $casts = [
        'is_shared' => 'boolean',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }
}
