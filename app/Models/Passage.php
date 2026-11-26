<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'horodatage_appareil' => 'datetime',
            'horodatage_serveur' => 'datetime',
        ];
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function pointControle(): BelongsTo
    {
        return $this->belongsTo(PointControle::class, 'point_controle_id');
    }
}