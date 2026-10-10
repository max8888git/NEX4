<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionLog extends RegistrarModel
{
    protected $table = 'transaction_logs';

    protected $primaryKey = 'log_id';

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
