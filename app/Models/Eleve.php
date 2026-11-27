<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Eleve extends Model
{
    protected $guarded = [];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function itineraire(): BelongsTo
    {
        return $this->belongsTo(Itineraire::class);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parents_eleves', 'eleve_id', 'parent_id');
    }

    public function badgeActif(): HasOne
    {
        return $this->hasOne(Badge::class)->where('etat', 'actif');
    }

    public function passages(): HasMany
    {
        return $this->hasMany(Passage::class);
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomalie::class);
    }
}