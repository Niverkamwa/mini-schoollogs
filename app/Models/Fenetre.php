<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fenetre extends Model
{
    protected $guarded = [];

    public function itineraire(): BelongsTo
    {
        return $this->belongsTo(Itineraire::class);
    }

    public function pointControle(): BelongsTo
    {
        return $this->belongsTo(PointControle::class, 'point_controle_id');
    }

    public function sAppliqueLeJour(int $jourIso): bool
    {
        $jours = array_filter(array_map('trim', explode(',', (string) $this->jours_application)));

        return in_array((string) $jourIso, $jours, true);
    }
}