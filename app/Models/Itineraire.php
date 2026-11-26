<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Itineraire extends Model
{
    protected $guarded = [];

    public function fenetres(): HasMany
    {
        return $this->hasMany(Fenetre::class)->orderBy('ordre');
    }

    public function eleves(): HasMany
    {
        return $this->hasMany(Eleve::class);
    }
}