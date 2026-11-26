<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageSortant extends Model
{
    protected $table = 'messages_sortants';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_envoi' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function anomalie(): BelongsTo
    {
        return $this->belongsTo(Anomalie::class, 'anomalie_id');
    }
}