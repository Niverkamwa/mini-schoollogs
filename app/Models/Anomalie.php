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
}