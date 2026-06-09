<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BellaromaConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'llave',
        'valor',
        'descripcion'
    ];
}