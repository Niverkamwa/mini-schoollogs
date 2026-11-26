<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anomalie extends Model
{
    protected $guarded = [];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
        public function fenetre(): BelongsTo
    {
        return $this->belongsTo(Fenetre::class);
    }

        public function passage(): BelongsTo
        {
            return $this->belongsTo(Passage::class);
        }
        public function messagesSortants(): HasMany
        {
            return $this->hasMany(MessageSortant::class, 'anomalie_id');
        }
}