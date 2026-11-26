<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'date_emission' => 'datetime',
        ];
    }
}