<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleGeneratorSetting extends RegistrarModel
{
    protected $table = 'schedule_generator_settings';

    protected $primaryKey = 'setting_id';

    public const CREATED_AT = null;

    protected $casts = [
        'keep_lunch' => 'boolean',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }
}
