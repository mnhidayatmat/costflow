<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wcc_record_id', 'user_id', 'from_status', 'to_status', 'note'])]
class WccStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(WccRecord::class, 'wcc_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
