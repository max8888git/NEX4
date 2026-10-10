<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TextScheduleRequest extends RegistrarModel
{
    protected $table = 'text_schedule_requests';

    protected $primaryKey = 'request_id';

    public const UPDATED_AT = null;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id');
    }
}
