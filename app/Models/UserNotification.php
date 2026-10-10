<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends RegistrarModel
{
    protected $table = 'user_notifications';

    protected $primaryKey = 'notification_id';

    public const UPDATED_AT = null;

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
